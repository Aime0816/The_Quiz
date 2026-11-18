<?php
header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");
ini_set("display_errors", "0");
require __DIR__ . "/game.php";
function respond(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit();
}
try {
    require __DIR__ . "/../conn.php";
    require __DIR__ . "/../includes/functions.php";
    $action = $_GET["action"] ?? "bootstrap";
    $method = $_SERVER["REQUEST_METHOD"];
    $post = $method === "POST";
    if (!in_array($method, ["GET", "POST"], true)) {
        respond(["error" => "Method not allowed."], 405);
    }
    if ($action === "view" && !$post) {
        session_write_close();
        $token = $_GET["token"] ?? "";
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            respond(["error" => "Invalid audience link."], 404);
        }
        $row = dbQuery(
            $conn,
            "SELECT revision,state_json FROM quiz_sessions WHERE viewer_token=?",
            "s",
            [$token],
        )
            ->get_result()
            ->fetch_assoc();
        if (!$row) {
            respond(["error" => "Session not found."], 404);
        }
        respond([
            "session" => publicGame(
                json_decode($row["state_json"], true, 512, JSON_THROW_ON_ERROR),
                false,
            ),
            "revision" => (int) $row["revision"],
        ]);
    }
    $user = currentUser();
    if (!$user) {
        respond(
            [
                "error" => "Please sign in to your staff account.",
                "login" => true,
            ],
            401,
        );
    }
    if (!in_array($user["role"], ["admin", "marker"], true)) {
        respond(
            ["error" => "Only markers and admins can control competitions."],
            403,
        );
    }
    if ($post && !validateCsrfToken($_SERVER["HTTP_X_CSRF_TOKEN"] ?? "")) {
        respond(
            ["error" => "Session expired. Reload before trying again."],
            403,
        );
    }
    $csrf = generateCsrfToken();
    session_write_close(); // Do not block other tabs while polling.
    if ($action === "bootstrap" && !$post) {
        $rows =
            $user["role"] === "admin"
                ? $conn
                    ->query(
                        "SELECT s.id,s.title,s.updated_at,u.username owner FROM quiz_sessions s LEFT JOIN admins u ON u.id=s.admin_id ORDER BY s.id DESC LIMIT 200",
                    )
                    ->fetch_all(MYSQLI_ASSOC)
                : dbQuery(
                    $conn,
                    "SELECT id,title,updated_at FROM quiz_sessions WHERE admin_id=? ORDER BY id DESC LIMIT 200",
                    "i",
                    [(int) $user["id"]],
                )
                    ->get_result()
                    ->fetch_all(MYSQLI_ASSOC);
        $bank = $conn
            ->query(
                "SELECT q.id,q.category,q.question,q.image,q.points,c.name categoryName FROM questions q JOIN categories c ON c.slug=q.category WHERE q.archived=0 AND c.archived=0 ORDER BY c.name,q.id",
            )
            ->fetch_all(MYSQLI_ASSOC);
        foreach ($bank as &$q) {
            $q["id"] = (int) $q["id"];
            $q["points"] = (int) $q["points"];
            $path = imagePath($q["image"]);
            $q["imageMissing"] =
                (bool) $q["image"] &&
                (!$path || !is_file(__DIR__ . "/../" . $path));
            unset($q["image"]);
        }
        unset($q);
        respond([
            "csrf" => $csrf,
            "sessions" => $rows,
            "username" => $user["username"],
            "role" => $user["role"],
            "bank" => $bank,
        ]);
    }
    $input = [];
    if ($post) {
        $input = json_decode(
            file_get_contents("php://input"),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        if (!is_array($input)) {
            respond(["error" => "Invalid JSON request."], 400);
        }
    }
    if ($action === "create" && $post) {
        $title = trim((string) ($input["title"] ?? ""));
        $names = $input["teams"] ?? [];
        $ids = $input["questionIds"] ?? [];
        if (
            !$title ||
            strlen($title) > 120 ||
            !is_array($names) ||
            count($names) < 2 ||
            count($names) > 24
        ) {
            rejectGame("Enter a title up to 120 bytes and 2–24 team names.");
        }
        $teams = [];
        $seen = [];
        foreach ($names as $i => $name) {
            if (!is_string($name)) {
                rejectGame("Invalid team name.");
            }
            $name = trim($name);
            if (
                !$name ||
                strlen($name) > 60 ||
                in_array(strtolower($name), $seen, true)
            ) {
                rejectGame("Use unique team names up to 60 bytes.");
            }
            $teams[] = [
                "id" => $i + 1,
                "name" => $name,
                "participating" => true,
                "eliminated" => false,
            ];
            $seen[] = strtolower($name);
        }
        if (!is_array($ids) || !$ids || count($ids) > 500) {
            rejectGame("Select between 1 and 500 questions for this session.");
        }
        $ids = array_values(array_unique(array_map("intval", $ids)));
        $marks = implode(",", array_fill(0, count($ids), "?"));
        $conn->begin_transaction();
        $questions = dbQuery(
            $conn,
            "SELECT q.id,q.category,q.question,q.answer,q.image,q.points,c.name categoryName FROM questions q JOIN categories c ON c.slug=q.category WHERE q.archived=0 AND c.archived=0 AND q.id IN ($marks) ORDER BY c.name,q.id",
            str_repeat("i", count($ids)),
            $ids,
        )
            ->get_result()
            ->fetch_all(MYSQLI_ASSOC);
        if (count($questions) !== count($ids)) {
            rejectGame(
                "A selected question was archived or removed. Reload the bank before creating the session.",
            );
        }
        $numbers = [];
        foreach ($questions as &$q) {
            if (
                !trim((string) $q["question"]) ||
                !trim((string) $q["answer"])
            ) {
                rejectGame(
                    "One selected question has no text or answer. Ask a setter to complete it.",
                );
            }
            $q["id"] = (int) $q["id"];
            $q["points"] = (int) $q["points"];
            if ($q["points"] < 1 || $q["points"] > 1000) {
                rejectGame("One selected question has an invalid point value.");
            }
            $q["image"] = imagePath($q["image"]);
            $numbers[$q["category"]] = ($numbers[$q["category"]] ?? 0) + 1;
            $q["number"] = $numbers[$q["category"]];
        }
        unset($q);
        $s = newGame($title, $teams, $questions);
        $token = bin2hex(random_bytes(24));
        dbQuery(
            $conn,
            "INSERT INTO quiz_sessions(admin_id,title,viewer_token,state_json) VALUES(?,?,?,?)",
            "isss",
            [
                (int) $user["id"],
                $title,
                $token,
                json_encode($s, JSON_THROW_ON_ERROR),
            ],
        );
        $id = $conn->insert_id;
        dbQuery(
            $conn,
            "INSERT INTO quiz_audit(session_id,actor_id,action,details_json) VALUES(?,?,?,?)",
            "iiss",
            [
                $id,
                (int) $user["id"],
                "create",
                json_encode(
                    ["questionIds" => $ids, "teams" => $names],
                    JSON_THROW_ON_ERROR,
                ),
            ],
        );
        $conn->commit();
        respond(["id" => $id], 201);
    }
    $id = (int) ($_GET["id"] ?? 0);
    if ($post) {
        $conn->begin_transaction();
    }
    $row = dbQuery(
        $conn,
        "SELECT * FROM quiz_sessions WHERE id=?" . ($post ? " FOR UPDATE" : ""),
        "i",
        [$id],
    )
        ->get_result()
        ->fetch_assoc();
    if (
        !$row ||
        ($user["role"] !== "admin" &&
            (int) $row["admin_id"] !== (int) $user["id"])
    ) {
        if ($post) {
            $conn->rollback();
        }
        respond(
            ["error" => "Session not found or not assigned to your account."],
            404,
        );
    }
    $s = normalizeGame(
        json_decode($row["state_json"], true, 512, JSON_THROW_ON_ERROR),
    );
    // Normalize valid legacy image prefixes when reading old sessions, without editing the bank.
    foreach ($s["questions"] as &$q) {
        $q["image"] = imagePath($q["image"] ?? "");
    }
    unset($q);
    if ($post) {
        if (
            !isset($input["revision"]) ||
            (int) $input["revision"] !== (int) $row["revision"]
        ) {
            $conn->rollback();
            respond(
                [
                    "error" =>
                        "Another control screen changed this session. The latest state is loading; check it before retrying.",
                ],
                409,
            );
        }
        $before = $s;
        $s = applyGameAction($s, $action, $input);
        $details = [
            "input" => array_intersect_key(
                $input,
                array_flip([
                    "question",
                    "team",
                    "outcome",
                    "points",
                    "reason",
                    "participating",
                    "name",
                    "theme",
                    "screen",
                    "category",
                    "open",
                    "revealed",
                ]),
            ),
            "revision" => (int) $row["revision"] + 1,
        ];
        if (in_array($action, ["judge", "correct_score"], true)) {
            $qid = (int) $input["question"];
            $details["before"] = $before["results"][$qid] ?? null;
            $details["after"] = $s["results"][$qid];
        }
        dbQuery(
            $conn,
            "UPDATE quiz_sessions SET state_json=?,revision=revision+1 WHERE id=?",
            "si",
            [json_encode($s, JSON_THROW_ON_ERROR), $id],
        );
        dbQuery(
            $conn,
            "INSERT INTO quiz_audit(session_id,actor_id,action,details_json) VALUES(?,?,?,?)",
            "iiss",
            [
                $id,
                (int) $user["id"],
                $action,
                json_encode($details, JSON_THROW_ON_ERROR),
            ],
        );
        $conn->commit();
        $row["revision"]++;
    } elseif ($action !== "session") {
        respond(["error" => "Unknown endpoint."], 404);
    }
    $audit = dbQuery(
        $conn,
        "SELECT a.id,a.action,a.details_json,a.created_at,u.username actor FROM quiz_audit a LEFT JOIN admins u ON u.id=a.actor_id WHERE a.session_id=? ORDER BY a.id DESC LIMIT 100",
        "i",
        [$id],
    )
        ->get_result()
        ->fetch_all(MYSQLI_ASSOC);
    foreach ($audit as &$entry) {
        $entry["details"] = json_decode($entry["details_json"], true);
        unset($entry["details_json"]);
    }
    unset($entry);
    respond([
        "session" => publicGame($s, true),
        "revision" => (int) $row["revision"],
        "viewerToken" => $row["viewer_token"],
        "audit" => $audit,
    ]);
} catch (JsonException $e) {
    respond(["error" => "Invalid JSON data."], 400);
} catch (DomainException $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    respond(["error" => $e->getMessage()], 422);
} catch (Throwable $e) {
    if (isset($conn) && $conn instanceof mysqli) {
        try {
            $conn->rollback();
        } catch (Throwable $ignored) {
        }
    }
    error_log((string) $e);
    respond(
        [
            "error" =>
                "Server error. Check the server log, database connection, and run php database/migrate.php.",
        ],
        500,
    );
}
