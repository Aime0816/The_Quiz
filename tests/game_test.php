<?php
require __DIR__ . "/../api/game.php";
function check($value, $message)
{
    if (!$value) {
        throw new Exception($message);
    }
}
function rejects($s, $action, $input = [])
{
    try {
        applyGameAction($s, $action, $input);
    } catch (DomainException $e) {
        return;
    }
    throw new Exception("Expected rejection: " . $action);
}
$teams = [
    ["id" => 1, "name" => "A", "participating" => true, "eliminated" => false],
    ["id" => 2, "name" => "B", "participating" => true, "eliminated" => false],
];
$qs = [];
for ($i = 1; $i <= 3; $i++) {
    $qs[] = [
        "id" => $i,
        "category" => "science",
        "categoryName" => "Science",
        "number" => $i,
        "points" => 15,
        "question" => "Question " . $i,
        "answer" => "secret" . $i,
        "image" => "uploads/questions/example.png",
    ];
}
$s = newGame("Test", $teams, $qs);
rejects($s, "pick", ["question" => 1]);
$s = applyGameAction($s, "start", []);
$s = applyGameAction($s, "pick", ["question" => 1]);
check(
    !isset(publicGame($s, false)["questions"][0]["answer"]),
    "Answer must not be sent before reveal",
);
check(
    !isset(publicGame($s, false)["results"]),
    "Audience must not receive private results",
);
rejects($s, "next");
rejects($s, "reveal");
$s = applyGameAction($s, "judge", [
    "question" => 1,
    "outcome" => "correct",
    "team" => 2,
]);
check(
    publicGame($s, true)["teams"][1]["score"] === 15,
    "Use configured points",
);
rejects($s, "judge", ["question" => 1, "outcome" => "correct", "team" => 2]);
$s = applyGameAction($s, "reveal", ["revealed" => true]);
check(
    publicGame($s, false)["questions"][0]["answer"] === "secret1",
    "Reveal current only",
);
check(
    !isset(publicGame($s, false)["questions"][1]["answer"]),
    "Future answers remain private",
);
$s = applyGameAction($s, "next", []);
rejects($s, "pick", ["question" => 1]);
$s = applyGameAction($s, "pick", ["question" => 2]);
$s = applyGameAction($s, "judge", [
    "question" => 2,
    "outcome" => "incorrect",
    "team" => 1,
]);
$s = applyGameAction($s, "next", []);
$s = applyGameAction($s, "pick", ["question" => 3]);
$s = applyGameAction($s, "judge", ["question" => 3, "outcome" => "skip"]);
check(
    publicGame($s, true)["teams"][0]["score"] === 0,
    "Wrong and skip earn zero",
);
$s = applyGameAction($s, "review", ["question" => 1]);
check(
    publicGame($s, true)["teams"][1]["score"] === 15,
    "Navigation must not change scores",
);
check(
    !isset(publicGame($s, false)["questions"][0]["answer"]),
    "Review must not automatically reveal",
);
$s = applyGameAction($s, "correct_score", [
    "question" => 1,
    "outcome" => "correct",
    "team" => 1,
    "reason" => "Wrong team selected",
]);
check(
    publicGame($s, true)["teams"][1]["score"] === 0 &&
        publicGame($s, true)["teams"][0]["score"] === 15,
    "Correction moves award, never adds a duplicate",
);
$s = applyGameAction($s, "eliminate", ["team" => 1]);
check(
    publicGame($s, true)["teams"][0]["score"] === 15,
    "Elimination preserves score",
);
$s = applyGameAction($s, "restore", ["team" => 1]);
$s = applyGameAction($s, "pause", []);
rejects($s, "pick", ["question" => 1]);
$s = applyGameAction($s, "resume", []);
$s = applyGameAction($s, "image", ["open" => true]);
check($s["presentation"]["imageOpen"], "Remote image open is saved");
$s = applyGameAction($s, "theme", ["theme" => "dark"]);
check($s["presentation"]["theme"] === "dark", "Audience theme saved");
$s = applyGameAction($s, "finish_session", []);
rejects($s, "judge", ["question" => 1, "outcome" => "correct", "team" => 1]);
$s = applyGameAction($s, "correct_score", [
    "question" => 1,
    "outcome" => "skip",
    "reason" => "Cancel wrong award",
]);
check(
    publicGame($s, true)["teams"][0]["score"] === 0,
    "Post-finish undo works",
);
$old = [
    "title" => "Old",
    "teams" => [["id" => 1, "name" => "A"], ["id" => 2, "name" => "B"]],
    "questions" => $qs,
    "activeTeam" => 1,
    "round" => null,
    "used" => [1],
    "history" => [
        [
            "question" => 1,
            "team" => 1,
            "winner" => 2,
            "points" => 5,
            "revealed" => true,
            "stage" => "judged",
        ],
    ],
    "ended" => true,
];
$converted = normalizeGame($old);
check(
    publicGame($converted, true)["teams"][1]["score"] === 5,
    "Legacy steal points preserved",
);
check($converted["legacyState"] === $old, "Legacy original retained");
check(
    !isset(publicGame($converted, false)["legacyState"]),
    "Legacy answers excluded from audience",
);
echo "PASS: game-rule checks, including legacy sessions and answer secrecy\n";
