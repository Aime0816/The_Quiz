<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('display_errors', '0');
require __DIR__.'/game.php';
function respond(array $data, int $code=200): void { http_response_code($code); echo json_encode($data, JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); exit; }
function queryDb(mysqli $db, string $sql, string $types='', array $args=[]): mysqli_stmt {
    $stmt=$db->prepare($sql);
    if ($types) $stmt->bind_param($types, ...$args);
    $stmt->execute(); return $stmt;
}
try {
    require __DIR__.'/../conn.php';
    require __DIR__.'/../includes/functions.php';
    $action=$_GET['action'] ?? 'bootstrap';
    $post=$_SERVER['REQUEST_METHOD']==='POST';
    $input=$post ? json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR) : [];
    if ($post && !is_array($input)) respond(['error'=>'Invalid request.'],400);
    if ($action==='view' && !$post) {
        $token=$_GET['token'] ?? '';
        if (!preg_match('/^[a-f0-9]{48}$/',$token)) respond(['error'=>'Invalid audience link.'],404);
        $row=queryDb($conn,'SELECT * FROM quiz_sessions WHERE viewer_token=?','s',[$token])->get_result()->fetch_assoc();
        if (!$row) respond(['error'=>'Session not found.'],404);
        respond(['session'=>publicGame(json_decode($row['state_json'],true,512,JSON_THROW_ON_ERROR),false),'revision'=>(int)$row['revision']]);
    }
    if (!isAdmin()) respond(['error'=>'Please sign in with your admin account.','login'=>true],401);
    if ($action==='bootstrap' && !$post) {
        $rows=queryDb($conn,'SELECT id,title,updated_at FROM quiz_sessions WHERE admin_id=? ORDER BY id DESC LIMIT 100','i',[(int)$_SESSION['admin_id']])->get_result()->fetch_all(MYSQLI_ASSOC);
        respond(['csrf'=>generateCsrfToken(),'sessions'=>$rows,'username'=>$_SESSION['admin_username'] ?? 'Host']);
    }
    if ($post && !validateCsrfToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) respond(['error'=>'Session expired. Reload this page.'],403);
    if ($action==='delete' && $post) {
        $id=(int)($_GET['id'] ?? 0);
        $stmt=queryDb($conn,'DELETE FROM quiz_sessions WHERE id=? AND admin_id=?','ii',[$id,(int)$_SESSION['admin_id']]);
        if ($stmt->affected_rows!==1) respond(['error'=>'Session not found.'],404);
        respond(['deleted'=>true]);
    }
    if ($action==='create' && $post) {
        $names=$input['teams'] ?? [];
        $title=trim((string)($input['title'] ?? ''));
        if (!$title || strlen($title)>120 || !is_array($names) || count($names)<2 || count($names)>12) rejectGame('Enter a title and between 2 and 12 teams.');
        $teams=[]; $seen=[];
        foreach ($names as $i=>$name) {
            if (!is_string($name)) rejectGame('Invalid team name.');
            $name=trim($name);
            if (!$name || strlen($name)>60 || in_array(strtolower($name),$seen,true)) rejectGame('Use unique team names, up to 60 characters each.');
            $teams[]=['id'=>$i+1,'name'=>$name]; $seen[]=strtolower($name);
        }
        // Snapshot all questions: legacy inactive means used, not deleted.
        $questions=$conn->query('SELECT id,category,question,answer,image FROM questions ORDER BY category,id')->fetch_all(MYSQLI_ASSOC);
        if (!$questions) rejectGame('Add questions in the admin dashboard first.');
        $numbers=[];
        foreach ($questions as &$q) { $q['id']=(int)$q['id']; $numbers[$q['category']]=($numbers[$q['category']] ?? 0)+1; $q['number']=$numbers[$q['category']]; }
        unset($q);
        $s=['title'=>$title,'teams'=>$teams,'questions'=>$questions,'activeTeam'=>1,'round'=>null,'used'=>[],'history'=>[],'ended'=>false];
        $token=bin2hex(random_bytes(24));
        queryDb($conn,'INSERT INTO quiz_sessions(admin_id,title,viewer_token,state_json) VALUES(?,?,?,?)','isss',[(int)$_SESSION['admin_id'],$title,$token,json_encode($s,JSON_THROW_ON_ERROR)]);
        respond(['id'=>$conn->insert_id]);
    }
    $id=(int)($_GET['id'] ?? 0);
    if ($post) $conn->begin_transaction();
    $row=queryDb($conn,'SELECT * FROM quiz_sessions WHERE id=? AND admin_id=?'.($post?' FOR UPDATE':''),'ii',[$id,(int)$_SESSION['admin_id']])->get_result()->fetch_assoc();
    if (!$row) { if ($post) $conn->rollback(); respond(['error'=>'Session not found.'],404); }
    $s=json_decode($row['state_json'],true,512,JSON_THROW_ON_ERROR);
    if ($post) {
        if (!isset($input['revision']) || (int)$input['revision']!==(int)$row['revision']) { $conn->rollback(); respond(['error'=>'This session changed. Refreshing it; please try again.'],409); }
        $s=applyGameAction($s,$action,$input);
        queryDb($conn,'UPDATE quiz_sessions SET state_json=?,revision=revision+1 WHERE id=?','si',[json_encode($s,JSON_THROW_ON_ERROR),$id]);
        $conn->commit(); $row['revision']++;
    } elseif ($action!=='session') respond(['error'=>'Unknown endpoint.'],404);
    respond(['session'=>publicGame($s,true),'revision'=>(int)$row['revision'],'viewerToken'=>$row['viewer_token']]);
} catch (DomainException $e) {
    if (isset($conn)) $conn->rollback();
    respond(['error'=>$e->getMessage()],422);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) { try { $conn->rollback(); } catch (Throwable $ignored) {} }
    error_log((string)$e);
    respond(['error'=>'Server error. Check the database connection and import database/upgrade.sql.'],500);
}
