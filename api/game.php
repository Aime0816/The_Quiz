<?php
// Pure game rules. Persistence and authentication live in index.php.
function rejectGame(string $message): void { throw new DomainException($message); }
function teamExists(array $state, int $id): bool {
    foreach ($state['teams'] as $team) if ($team['id'] === $id) return true;
    return false;
}
function applyGameAction(array $s, string $action, array $input): array {
    if ($s['ended']) rejectGame('This session has ended.');
    $r = $s['round'];
    switch ($action) {
        case 'select_team':
            if ($r !== null) rejectGame('Finish the current question first.');
            $id = (int)($input['team'] ?? 0);
            if (!teamExists($s, $id)) rejectGame('Choose a valid team.');
            $s['activeTeam'] = $id;
            break;
        case 'pick':
            if ($r !== null) rejectGame('Finish the current question first.');
            $id = (int)($input['question'] ?? 0);
            if (in_array($id, $s['used'], true)) rejectGame('That question is already used.');
            $found = false;
            foreach ($s['questions'] as $q) if ($q['id'] === $id) $found = true;
            if (!$found) rejectGame('Question not found in this session.');
            $s['round'] = ['question'=>$id, 'team'=>$s['activeTeam'], 'stage'=>'answering', 'stealTeam'=>null, 'winner'=>null, 'points'=>0, 'revealed'=>false];
            break;
        case 'correct':
        case 'wrong':
            if (!$r || !in_array($r['stage'], ['answering','stealing'], true)) rejectGame('This answer has already been judged.');
            if ($action === 'correct') {
                $r['winner'] = $r['stage'] === 'stealing' ? $r['stealTeam'] : $r['team'];
                $r['points'] = $r['stage'] === 'stealing' ? 5 : 10;
                $r['stage'] = 'judged';
            } else {
                $r['stage'] = $r['stage'] === 'answering' ? 'wrong' : 'judged';
            }
            $s['round'] = $r;
            break;
        case 'pass':
            if (!$r || $r['stage'] !== 'wrong' || $r['revealed']) rejectGame('Only an unrevealed wrong answer can be passed.');
            $id = (int)($input['team'] ?? 0);
            if (!teamExists($s,$id) || $id === $r['team']) rejectGame('Choose another team.');
            $r['stealTeam'] = $id;
            $r['stage'] = 'stealing';
            $s['round'] = $r;
            break;
        case 'reveal':
            if (!$r || !in_array($r['stage'], ['wrong','judged'], true) || $r['revealed']) rejectGame('Judge the answer before revealing it.');
            $r['stage'] = 'judged';
            $r['revealed'] = true;
            $s['round'] = $r;
            break;
        case 'finish':
            if (!$r || !$r['revealed']) rejectGame('Reveal the answer first.');
            $s['history'][] = $r;
            $s['used'][] = $r['question'];
            $s['round'] = null;
            $ids = array_column($s['teams'],'id');
            $s['activeTeam'] = $ids[(array_search($r['team'],$ids,true)+1)%count($ids)];
            break;
        case 'undo':
            if ($r) {
                // Before reveal a mistaken judgment can be retried. After reveal,
                // cancellation closes the question with zero points: no answer reuse.
                if ($r['revealed']) {
                    $r['winner']=null; $r['points']=0;
                    $s['round']=$r;
                } else {
                    $r['stage']='answering'; $r['stealTeam']=null; $r['winner']=null; $r['points']=0;
                    $s['round']=$r;
                }
            } else {
                if (!$s['history']) rejectGame('Nothing to undo.');
                $last = count($s['history'])-1;
                $s['history'][$last]['winner']=null;
                $s['history'][$last]['points']=0;
            }
            break;
        case 'end':
            if ($r) rejectGame('Finish the current question first.');
            $s['ended'] = true;
            break;
        default: rejectGame('Unknown action.');
    }
    return $s;
}
function publicGame(array $s, bool $host): array {
    foreach ($s['teams'] as &$team) {
        $team['score']=0;
        foreach ($s['history'] as $r) if ($r['winner'] === $team['id']) $team['score'] += $r['points'];
        if ($s['round'] && $s['round']['winner'] === $team['id']) $team['score'] += $s['round']['points'];
    }
    unset($team);
    foreach ($s['questions'] as &$q) {
        $current = $s['round'] && $s['round']['question'] === $q['id'];
        if (!$host && (!$current || !$s['round']['revealed'])) unset($q['answer']);
        if (!$host && !$current) { unset($q['question'], $q['image']); }
    }
    unset($q);
    return $s;
}
