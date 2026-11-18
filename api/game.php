<?php
/** Pure competition rules: the API handles authentication, locks, and audit persistence. */
function rejectGame(string $message): void
{
    throw new DomainException($message);
}
function questionIn(array $s, int $id): array
{
    foreach ($s["questions"] as $q) {
        if ($q["id"] === $id) {
            return $q;
        }
    }
    rejectGame("Question not found in this session.");
}
function teamIn(array $s, int $id, bool $mustBeActive = true): array
{
    foreach ($s["teams"] as $team) {
        if ($team["id"] === $id) {
            if (
                $mustBeActive &&
                (!$team["participating"] || $team["eliminated"])
            ) {
                rejectGame("Choose a participating, non-eliminated team.");
            }
            return $team;
        }
    }
    rejectGame("Choose a valid team.");
}
function defaultPresentation(): array
{
    return [
        "screen" => "categories",
        "category" => "",
        "question" => null,
        "revealed" => false,
        "imageOpen" => false,
        "imageVersion" => 0,
        "theme" => "light",
    ];
}
/** Read older saved sessions without changing their source rows or losing their original history. */
function normalizeGame(array $s): array
{
    if (($s["schemaVersion"] ?? 0) === 4) {
        return $s;
    }
    $legacy = $s;
    $s["schemaVersion"] = 4;
    $s["status"] = !empty($s["ended"]) ? "finished" : "live";
    foreach ($s["teams"] as &$t) {
        $t["id"] = (int) $t["id"];
        $t["participating"] = true;
        $t["eliminated"] = false;
    }
    unset($t);
    foreach ($s["questions"] as &$q) {
        $q["id"] = (int) $q["id"];
        $q["points"] = (int) ($q["points"] ?? 10);
        $q["categoryName"] = $q["categoryName"] ?? $q["category"];
    }
    unset($q);
    $s["results"] = [];
    $s["history"] = [];
    $s["used"] = [];
    $s["current"] = null;
    $s["presentation"] = defaultPresentation();
    foreach ($legacy["history"] ?? [] as $r) {
        $id = (int) $r["question"];
        $s["history"][] = $id;
        $s["used"][] = $id;
        $s["results"][$id] = [
            "question" => $id,
            "team" => $r["winner"] ?? $r["team"],
            "outcome" => ($r["points"] ?? 0) > 0 ? "correct" : "incorrect",
            "points" => (int) ($r["points"] ?? 0),
            "version" => 1,
        ];
    }
    if (!empty($legacy["round"])) {
        $r = $legacy["round"];
        $id = (int) $r["question"];
        $q = questionIn($s, $id);
        $s["current"] = $id;
        $s["used"][] = $id;
        $s["history"][] = $id;
        $s["presentation"] = array_merge(defaultPresentation(), [
            "screen" => "question",
            "category" => $q["category"],
            "question" => $id,
            "revealed" => (bool) $r["revealed"],
        ]);
        if ($r["stage"] === "judged" || $r["revealed"]) {
            $s["results"][$id] = [
                "question" => $id,
                "team" => $r["winner"] ?? $r["team"],
                "outcome" => ($r["points"] ?? 0) > 0 ? "correct" : "incorrect",
                "points" => (int) ($r["points"] ?? 0),
                "version" => 1,
            ];
        }
    }
    if ($s["status"] === "finished") {
        $s["presentation"]["screen"] = "scoreboard";
    }
    $s["legacyState"] = $legacy; // Never returned to the audience.
    unset($s["round"], $s["ended"]);
    return $s;
}
function newGame(string $title, array $teams, array $questions): array
{
    return [
        "schemaVersion" => 4,
        "title" => $title,
        "teams" => $teams,
        "questions" => $questions,
        "status" => "setup",
        "current" => null,
        "used" => [],
        "history" => [],
        "results" => [],
        "presentation" => defaultPresentation(),
    ];
}
function scoreResult(
    array $s,
    int $qid,
    array $input,
    bool $correction = false,
): array {
    $q = questionIn($s, $qid);
    $outcome = $input["outcome"] ?? "";
    if (!in_array($outcome, ["correct", "incorrect", "skip"], true)) {
        rejectGame("Choose Correct, Incorrect, or No answer / skip.");
    }
    $team = (int) ($input["team"] ?? 0);
    if ($team) {
        $receiving = teamIn($s, $team, !$correction);
        if (!$receiving["participating"]) {
            rejectGame("Choose a participating team.");
        }
    }
    if ($outcome !== "skip" && !$team) {
        rejectGame("Choose the answering team privately first.");
    }
    $points = 0;
    if ($outcome === "correct") {
        // Markers may award their own marks instead of the question's default.
        $points = array_key_exists("points", $input)
            ? (int) $input["points"]
            : (int) $q["points"];
        if ($points < 1 || $points > 1000) {
            rejectGame("Enter the marks to award as a whole number from 1 to 1000.");
        }
    }
    return [
        "question" => $qid,
        "team" => $team ?: null,
        "outcome" => $outcome,
        "points" => $points,
        "version" => (int) ($s["results"][$qid]["version"] ?? 0) + 1,
    ];
}
function applyGameAction(array $s, string $action, array $input): array
{
    $s = normalizeGame($s);
    $p = $s["presentation"];
    // Corrections and presentation controls remain available after the final whistle.
    $presentationActions = [
        "theme",
        "image",
        "reveal",
        "screen",
        "category",
        "review",
    ];
    if (
        $s["status"] === "finished" &&
        !in_array(
            $action,
            array_merge($presentationActions, ["correct_score"]),
            true,
        )
    ) {
        rejectGame(
            "This session has finished. Use score correction for a recorded result.",
        );
    }
    switch ($action) {
        case "add_team":
            if ($s["status"] !== "setup") {
                rejectGame("Register teams before starting.");
            }
            $name = trim((string) ($input["name"] ?? ""));
            if (!$name || strlen($name) > 60 || count($s["teams"]) >= 24) {
                rejectGame(
                    "Use a team name up to 60 bytes. Maximum 24 registered teams.",
                );
            }
            foreach ($s["teams"] as $t) {
                if (strtolower($t["name"]) === strtolower($name)) {
                    rejectGame("That team name is already registered.");
                }
            }
            $s["teams"][] = [
                "id" => count($s["teams"]) + 1,
                "name" => $name,
                "participating" => true,
                "eliminated" => false,
            ];
            break;
        case "participation":
            if ($s["status"] !== "setup") {
                rejectGame("Choose participants before starting.");
            }
            $id = (int) ($input["team"] ?? 0);
            teamIn($s, $id, false);
            foreach ($s["teams"] as &$t) {
                if ($t["id"] === $id) {
                    $t["participating"] =
                        (bool) ($input["participating"] ?? false);
                }
            }
            unset($t);
            break;
        case "start":
            if ($s["status"] !== "setup") {
                rejectGame("This session has already started.");
            }
            if (
                count(
                    array_filter($s["teams"], fn($t) => $t["participating"]),
                ) < 2
            ) {
                rejectGame("Select at least two participating teams.");
            }
            if (!$s["questions"]) {
                rejectGame("Assign questions before starting.");
            }
            $s["status"] = "live";
            break;
        case "pause":
            if ($s["status"] !== "live") {
                rejectGame("Only a live session can be paused.");
            }
            $s["status"] = "paused";
            break;
        case "resume":
            if ($s["status"] !== "paused") {
                rejectGame("This session is not paused.");
            }
            $s["status"] = "live";
            break;
        case "finish_session":
            if (!in_array($s["status"], ["live", "paused"], true)) {
                rejectGame("Start the session first.");
            }
            if ($s["current"] && !isset($s["results"][$s["current"]])) {
                rejectGame("Judge or skip the open question before finishing.");
            }
            $s["status"] = "finished";
            $s["current"] = null;
            $p["screen"] = "scoreboard";
            $p["revealed"] = false;
            $p["imageOpen"] = false;
            break;
        case "category":
            if ($s["status"] === "setup") {
                rejectGame("Start the session first.");
            }
            $category = (string) ($input["category"] ?? "");
            if (
                !in_array(
                    $category,
                    array_column($s["questions"], "category"),
                    true,
                )
            ) {
                rejectGame("Choose a category assigned to this session.");
            }
            $p["category"] = $category;
            $p["screen"] = "numbers";
            $p["question"] = null;
            $p["revealed"] = false;
            $p["imageOpen"] = false;
            break;
        case "screen":
            $screen = $input["screen"] ?? "";
            if (
                !in_array(
                    $screen,
                    ["categories", "numbers", "scoreboard", "question"],
                    true,
                )
            ) {
                rejectGame("Invalid presentation screen.");
            }
            if ($screen === "numbers" && !$p["category"]) {
                rejectGame("Select a category first.");
            }
            if ($screen === "question") {
                if (!$s["current"]) {
                    rejectGame("There is no current question.");
                }
                $p["question"] = $s["current"];
                $p["category"] = questionIn($s, $s["current"])["category"];
            } else {
                $p["question"] = null;
                $p["revealed"] = false;
            }
            $p["screen"] = $screen;
            $p["imageOpen"] = false;
            break;
        case "pick":
            if ($s["status"] !== "live") {
                rejectGame(
                    "Resume or start the session before opening a new question.",
                );
            }
            if ($s["current"] !== null) {
                rejectGame(
                    "Finish the current question with Next question first.",
                );
            }
            $id = (int) ($input["question"] ?? 0);
            $q = questionIn($s, $id);
            if (in_array($id, $s["used"], true)) {
                rejectGame(
                    "This question has already been used. Review it from history instead.",
                );
            }
            $s["current"] = $id;
            $s["used"][] = $id;
            $s["history"][] = $id;
            $p = array_merge($p, [
                "screen" => "question",
                "category" => $q["category"],
                "question" => $id,
                "revealed" => false,
                "imageOpen" => false,
            ]);
            break;
        case "judge":
            if ($s["status"] !== "live") {
                rejectGame("Resume the session before judging.");
            }
            $id = (int) ($input["question"] ?? 0);
            if (
                !$s["current"] ||
                $id !== $s["current"] ||
                $p["question"] !== $id ||
                $p["screen"] !== "question"
            ) {
                rejectGame("Return to the current question before judging.");
            }
            if (isset($s["results"][$id])) {
                rejectGame(
                    "This question has already been scored. Use Correct score to change its result.",
                );
            }
            $s["results"][$id] = scoreResult($s, $id, $input);
            break;
        case "next":
            if (!$s["current"] || !isset($s["results"][$s["current"]])) {
                rejectGame("Judge or skip the question first.");
            }
            $p["category"] = questionIn($s, $s["current"])["category"];
            $s["current"] = null;
            $p["screen"] = "numbers";
            $p["question"] = null;
            $p["revealed"] = false;
            $p["imageOpen"] = false;
            break;
        case "review":
            $id = (int) ($input["question"] ?? 0);
            $q = questionIn($s, $id);
            if (!in_array($id, $s["history"], true)) {
                rejectGame("Only previously opened questions can be reviewed.");
            }
            $p = array_merge($p, [
                "screen" => "question",
                "category" => $q["category"],
                "question" => $id,
                "revealed" => false,
                "imageOpen" => false,
            ]);
            break;
        case "reveal":
            if ($p["screen"] !== "question" || !$p["question"]) {
                rejectGame("Show a question first.");
            }
            if (!isset($s["results"][$p["question"]])) {
                rejectGame(
                    "Judge or skip the question before revealing its answer.",
                );
            }
            $p["revealed"] = (bool) ($input["revealed"] ?? true);
            break;
        case "image":
            if (
                $p["screen"] !== "question" ||
                !$p["question"] ||
                empty(questionIn($s, $p["question"])["image"])
            ) {
                rejectGame("This screen has no question image.");
            }
            $p["imageOpen"] = (bool) ($input["open"] ?? false);
            $p["imageVersion"]++;
            break;
        case "theme":
            if (!in_array($input["theme"] ?? "", ["light", "dark"], true)) {
                rejectGame("Choose light or dark.");
            }
            $p["theme"] = $input["theme"];
            break;
        case "eliminate":
        case "restore":
            if ($s["status"] === "setup") {
                rejectGame("Use participation checkboxes during setup.");
            }
            $id = (int) ($input["team"] ?? 0);
            $t = teamIn($s, $id, false);
            if (!$t["participating"]) {
                rejectGame("That team is not participating.");
            }
            if ($t["eliminated"] === ($action === "eliminate")) {
                rejectGame("Team status is already set.");
            }
            foreach ($s["teams"] as &$t) {
                if ($t["id"] === $id) {
                    $t["eliminated"] = $action === "eliminate";
                }
            }
            unset($t);
            break;
        case "correct_score":
            $id = (int) ($input["question"] ?? 0);
            if (!isset($s["results"][$id])) {
                rejectGame("There is no recorded score for this question.");
            }
            $reason = trim((string) ($input["reason"] ?? ""));
            if (strlen($reason) < 3 || strlen($reason) > 500) {
                rejectGame("Explain this correction in 3–500 bytes.");
            }
            $s["results"][$id] = scoreResult($s, $id, $input, true);
            break;
        default:
            rejectGame("Unknown competition action.");
    }
    $s["presentation"] = $p;
    return $s;
}
/** Deliberate allowlist. Never return the saved state JSON wholesale to viewers. */
function publicGame(array $s, bool $host): array
{
    $s = normalizeGame($s);
    $teams = [];
    foreach ($s["teams"] as $t) {
        $t["score"] = 0;
        foreach ($s["results"] as $r) {
            if ($r["team"] === $t["id"]) {
                $t["score"] += $r["points"];
            }
        }
        // Eliminated teams disappear from the audience screen but stay with the marker.
        if ($host || ($t["participating"] && !$t["eliminated"])) {
            $teams[] = $t;
        }
    }
    $questions = [];
    $p = $s["presentation"];
    foreach ($s["questions"] as $q) {
        $item = [
            "id" => $q["id"],
            "category" => $q["category"],
            "categoryName" => $q["categoryName"],
            "number" => $q["number"],
            "points" => $q["points"],
        ];
        $visible =
            $p["screen"] === "question" &&
            $p["question"] === $q["id"] &&
            $s["status"] !== "setup";
        if ($host || $visible) {
            $item["question"] = $q["question"];
            $item["image"] = $q["image"];
        }
        if ($host || ($visible && $p["revealed"])) {
            $item["answer"] = $q["answer"];
        }
        $questions[] = $item;
    }
    $out = [
        "schemaVersion" => 4,
        "title" => $s["title"],
        "status" => $s["status"],
        "teams" => $teams,
        "questions" => $questions,
        "used" => $s["used"],
        "presentation" => $p,
    ];
    if ($host) {
        $out["current"] = $s["current"];
        $out["history"] = $s["history"];
        $out["results"] = (object) $s["results"];
    }
    return $out;
}
