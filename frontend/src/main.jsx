import React, { useEffect, useRef, useState } from "react";
import { createRoot } from "react-dom/client";
import "./style.css";
import { root, viewerToken, audience, request } from "./api.js";
import {
  ImageView,
  ImageDialog,
  AnswerDialog,
} from "./components/ImageView.jsx";
import Scoreboard from "./components/Scoreboard.jsx";
import Setup from "./components/Setup.jsx";
import Correction from "./components/Correction.jsx";
function savedTheme() {
  try {
    return localStorage.getItem("quiz-workspace-theme") || "light";
  } catch {
    return "light";
  }
}
function App() {
  const [boot, setBoot] = useState(null),
    [id, setId] = useState(new URLSearchParams(location.search).get("session")),
    [data, setData] = useState(null);
  const [busy, setBusy] = useState(false),
    [error, setError] = useState(""),
    [syncError, setSyncError] = useState(""),
    [feedback, setFeedback] = useState(""),
    [login, setLogin] = useState(false);
  const [theme, setTheme] = useState(savedTheme),
    [team, setTeam] = useState(""),
    [newTeam, setNewTeam] = useState(""),
    [correction, setCorrection] = useState(null),
    [marks, setMarks] = useState(""),
    [imageOpen, setImageOpen] = useState(false);
  const lock = useRef(false),
    epoch = useRef(0),
    version = useRef(-1);
  const s = data?.session,
    p = s?.presentation,
    q = s?.questions.find((q) => q.id === p?.question),
    r = s?.results?.[q?.id];
  const disabled = busy || !!syncError || (!audience && !boot);
  const categories = s
    ? [
        ...new Map(
          s.questions.map((q) => [q.category, q.categoryName]),
        ).entries(),
      ]
    : [];
  const fail = (e) => {
    setError(e.message);
    if (e.status === 401) setLogin(true);
  };
  const loadBoot = () =>
    request("bootstrap")
      .then((d) => {
        setBoot(d);
        setLogin(false);
        setError("");
      })
      .catch(fail);
  useEffect(() => {
    if (!audience) loadBoot();
  }, []);
  useEffect(() => {
    document.documentElement.dataset.theme = audience
      ? p?.theme || "light"
      : theme;
    document.body.classList.toggle("audience", audience);
  }, [theme, p?.theme]);
  useEffect(() => {
    setTeam("");
    setCorrection(null);
    setMarks(q ? String(q.points) : "");
  }, [q?.id, q?.points]);
  useEffect(() => {
    if (!feedback) return;
    const timer = setTimeout(() => setFeedback(""), 5000);
    return () => clearTimeout(timer);
  }, [feedback]);
  useEffect(() => {
    if (correction)
      document
        .getElementById("correction-panel")
        ?.scrollIntoView({ block: "center" });
  }, [correction]);
  useEffect(() => {
    setImageOpen(Boolean(p?.imageOpen));
  }, [p?.imageOpen, p?.imageVersion, p?.question]);
  const accept = (d) => {
    if (d.revision >= version.current) {
      version.current = d.revision;
      setData(d);
    }
  };
  useEffect(() => {
    setData(null);
    version.current = -1;
    if (!id && !audience) return;
    let active = true,
      timer;
    const poll = async () => {
      if (!lock.current) {
        const ticket = ++epoch.current;
        try {
          const d = await request(audience ? "view" : "session", { id });
          if (active && ticket === epoch.current) {
            accept(d);
            setSyncError("");
          }
        } catch (e) {
          if (active) {
            setSyncError(e.message);
            if (e.status === 401) setLogin(true);
          }
        }
      }
      if (active) timer = setTimeout(poll, 1500);
    };
    poll();
    return () => {
      active = false;
      clearTimeout(timer);
      ++epoch.current;
    };
  }, [id]);
  const act = async (action, extra = {}) => {
    if (lock.current || !boot || !data || syncError) return false;
    lock.current = true;
    setBusy(true);
    setError("");
    setFeedback("");
    ++epoch.current;
    try {
      const d = await request(action, {
        id,
        csrf: boot.csrf,
        body: { revision: data.revision, ...extra },
      });
      accept(d);
      setFeedback(
        action === "judge"
          ? "Judgment saved. Scores updated."
          : action === "correct_score"
            ? "Score corrected. Change recorded."
            : "Saved and synchronized.",
      );
      return true;
    } catch (e) {
      fail(e);
      try {
        accept(await request("session", { id }));
      } catch (refreshError) {
        setSyncError(refreshError.message);
      }
      return false;
    } finally {
      lock.current = false;
      setBusy(false);
    }
  };
  const open = (next) => {
    const url = new URL(location.href);
    url.searchParams.set("session", next);
    history.replaceState(null, "", url);
    setId(String(next));
    setError("");
    setSyncError("");
    setFeedback("");
    setCorrection(null);
  };
  const home = () => {
    const url = new URL(location.href);
    url.searchParams.delete("session");
    history.replaceState(null, "", url);
    setId(null);
    setError("");
    setSyncError("");
    setFeedback("");
    setData(null);
    loadBoot();
  };
  const create = async (input) => {
    if (lock.current) return;
    lock.current = true;
    setBusy(true);
    setError("");
    try {
      const d = await request("create", { csrf: boot.csrf, body: input });
      open(d.id);
    } catch (e) {
      fail(e);
    } finally {
      lock.current = false;
      setBusy(false);
    }
  };
  const toggleTheme = () => {
    const next = theme === "dark" ? "light" : "dark";
    setTheme(next);
    try {
      localStorage.setItem("quiz-workspace-theme", next);
    } catch {}
  };
  const fullscreen = async () => {
    try {
      if (document.fullscreenElement) await document.exitFullscreen();
      else await document.documentElement.requestFullscreen();
    } catch {
      setError(
        "Fullscreen is unavailable. Use your browser’s fullscreen control.",
      );
    }
  };
  const closeImage = () => {
    setImageOpen(false);
    if (!audience && p?.imageOpen) act("image", { open: false });
  };
  const button = (label, action, args = {}, cls = "") => (
    <button
      className={cls}
      disabled={disabled}
      onClick={() => act(action, args)}
    >
      {label}
    </button>
  );
  const teamRow = (t) => (
    <div
      key={t.id}
      className={"team-manage-row" + (t.eliminated ? " eliminated" : "")}
    >
      <div>
        <b>{t.name}</b>
        <small>
          {!t.participating
            ? "Not selected"
            : t.eliminated
              ? "Eliminated"
              : "Competing"}{" "}
          · {t.score} points
        </small>
      </div>
      {s.status !== "setup" &&
        s.status !== "finished" &&
        t.participating && (
          <button
            disabled={disabled}
            onClick={() => {
              if (
                confirm(
                  `${t.eliminated ? "Restore" : "Eliminate"} ${t.name}? Their scores and history will be retained.`,
                )
              )
                act(t.eliminated ? "restore" : "eliminate", {
                  team: t.id,
                });
            }}
          >
            {t.eliminated ? "Restore" : "Eliminate"}
          </button>
        )}
    </div>
  );
  const eliminated = s ? s.teams.filter((t) => t.eliminated) : [];
  const previous = s?.history?.slice(0, s.history.indexOf(q?.id)).at(-1);
  return (
    <>
      <a className="skip-link" href="#content">
        Skip to content
      </a>
      <header className="topbar">
        <a className="brand" href={audience ? "#" : root + "dashboard.php"}>
          <span className="brand-mark">Q</span>
          <span>
            THE QUIZ
            <small>{audience ? "LIVE AUDIENCE" : "MARKER WORKSPACE"}</small>
          </span>
        </a>
        <div className="top-actions">
          {!audience && (
            <>
              <span className="user-label">{boot?.username}</span>
              <button onClick={toggleTheme}>
                {theme === "dark" ? "Light mode" : "Dark mode"}
              </button>
              {boot && (
                <form action={root + "logout.php"} method="post">
                  <input type="hidden" name="csrf_token" value={boot.csrf} />
                  <button>Sign out</button>
                </form>
              )}
            </>
          )}
          <button onClick={fullscreen}>⛶ Fullscreen</button>
        </div>
      </header>
      <main id="content">
        <div className="connection" role="status">
          <span className={"connection-dot " + (syncError ? "offline" : "")} />
          {syncError
            ? "Connection lost · showing last saved state"
            : s
              ? "Synchronized · updates automatically"
              : audience
                ? "Connecting to the competition…"
                : "Your private competition workspace"}
        </div>
        {syncError && (
          <div className="notice error" role="alert">
            {syncError} Reconnecting automatically. Scoring is disabled until
            synchronization returns.
          </div>
        )}
        {error && (
          <div className="notice error" role="alert">
            {error}
            {!audience && !boot && <button onClick={loadBoot}>Retry</button>}
            <button
              className="dismiss"
              aria-label="Dismiss message"
              onClick={() => setError("")}
            >
              ×
            </button>
          </div>
        )}
        {login && (
          <div className="notice">
            <a href={root + "login.php"}>Sign in to continue →</a>
          </div>
        )}
        {!audience && feedback && (
          <div className="feedback" role="status">
            ✓ {feedback}
          </div>
        )}
        {!audience && !id && boot && (
          <>
            <div className="page-heading">
              <div>
                <p className="eyebrow">SET THE STAGE</p>
                <h1>Let the questions begin.</h1>
                <p>
                  Prepare the round. Gather your teams. Keep the room in sync.
                </p>
              </div>
              {boot.role === "admin" && (
                <a className="button" href={root + "dashboard.php"}>
                  Admin dashboard ↗
                </a>
              )}
            </div>
            <Setup boot={boot} busy={busy} onCreate={create} />
            <section className="card saved-sessions">
              <p className="eyebrow">PICK UP WHERE YOU LEFT OFF</p>
              <h2>Saved competitions</h2>
              {!boot.sessions.length && (
                <p>Your saved sessions will appear here.</p>
              )}
              {boot.sessions.map((row) => (
                <button
                  key={row.id}
                  className="session-row"
                  onClick={() => open(row.id)}
                >
                  <span>
                    <b>{row.title}</b>
                    <small>
                      {row.owner ? row.owner + " · " : ""}Last saved{" "}
                      {row.updated_at}
                    </small>
                  </span>
                  <span>Open →</span>
                </button>
              ))}
            </section>
          </>
        )}
        {!audience && id && !s && (
          <button onClick={home}>← Back to sessions</button>
        )}
        {s && (
          <>
            <div className="page-heading session-heading">
              <div>
                <p className="eyebrow">
                  {audience ? "SCHOOL QUIZ COMPETITION" : "PRIVATE HOST DESK"}{" "}
                  <span className="pill">{s.status}</span>
                </p>
                <h1>{s.title}</h1>
                <p>
                  {s.used.length} of {s.questions.length} questions opened
                </p>
              </div>
              {!audience && (
                <div className="actions">
                  <button onClick={home} disabled={busy}>
                    ← Sessions
                  </button>
                  <a
                    className="button primary"
                    target="_blank"
                    rel="noopener noreferrer"
                    href={root + "live/?view=" + data.viewerToken}
                  >
                    Open audience screen ↗
                  </a>
                </div>
              )}
            </div>
            {!audience && (
              <section
                className="control-bar card"
                aria-label="Presentation controls"
              >
                <div>
                  <p className="eyebrow">PRESENTATION</p>
                  <div className="actions">
                    {button("Categories", "screen", { screen: "categories" })}
                    {p.category &&
                      button("Question numbers", "screen", {
                        screen: "numbers",
                      })}
                    {button("Scoreboard", "screen", { screen: "scoreboard" })}
                    {s.current &&
                      button("Current question", "screen", {
                        screen: "question",
                      })}
                  </div>
                </div>
                <div className="session-actions">
                  <label>
                    Audience theme
                    <select
                      aria-label="Audience theme"
                      value={p.theme}
                      disabled={disabled}
                      onChange={(e) => act("theme", { theme: e.target.value })}
                    >
                      <option value="light">Light</option>
                      <option value="dark">Dark</option>
                    </select>
                  </label>
                  {s.status === "live" && button("Pause session", "pause")}
                  {s.status === "paused" &&
                    button("Resume session", "resume", {}, "primary")}
                  {["live", "paused"].includes(s.status) && (
                    <button
                      className="danger"
                      disabled={disabled}
                      onClick={() => {
                        if (
                          confirm(
                            "Finish this competition and show the final scoreboard? You can still correct recorded scores.",
                          )
                        )
                          act("finish_session");
                      }}
                    >
                      Finish session
                    </button>
                  )}
                </div>
              </section>
            )}
            {s.status === "paused" && (
              <div className="pause-banner" role="status">
                Ⅱ Competition paused
                {!audience ? " · resume to open or judge questions" : ""}
              </div>
            )}
            {s.status === "setup" ? (
              <div className="card waiting">
                <p className="eyebrow">READY WHEN YOU ARE</p>
                <h2>
                  {audience
                    ? "The competition will begin shortly."
                    : "Confirm your participating teams."}
                </h2>
                {!audience && (
                  <>
                    <div className="team-setup">
                      {s.teams.map((t) => (
                        <label className="check" key={t.id}>
                          <input
                            type="checkbox"
                            checked={t.participating}
                            disabled={disabled}
                            onChange={(e) =>
                              act("participation", {
                                team: t.id,
                                participating: e.target.checked,
                              })
                            }
                          />
                          {t.name}
                          <span className="pill">
                            {t.participating ? "Participating" : "Not selected"}
                          </span>
                        </label>
                      ))}
                    </div>
                    <form
                      className="actions"
                      onSubmit={async (e) => {
                        e.preventDefault();
                        if (await act("add_team", { name: newTeam }))
                          setNewTeam("");
                      }}
                    >
                      <label>
                        Register another team
                        <input
                          value={newTeam}
                          onChange={(e) => setNewTeam(e.target.value)}
                          maxLength={60}
                          required
                        />
                      </label>
                      <button disabled={disabled}>Add team</button>
                    </form>
                    <p>
                      {s.questions.length} questions saved across{" "}
                      {categories.length} categories. Connect the projector
                      before starting.
                    </p>
                    {button("Start competition →", "start", {}, "primary")}
                  </>
                )}
              </div>
            ) : (
              <div
                className={
                  "game-layout " +
                  (p.screen === "scoreboard" ? "scoreboard-layout" : "")
                }
              >
                <section className="board" aria-label="Presentation">
                  {p.screen === "scoreboard" ? (
                    <Scoreboard s={s} />
                  ) : p.screen === "question" && q ? (
                    <article className="card question-card">
                      <div className="question-meta">
                        <span>
                          {q.categoryName} / Question{" "}
                          {String(q.number).padStart(2, "0")}
                        </span>
                        <span>{q.points} points</span>
                      </div>
                      {!audience && s.current !== q.id && (
                        <p className="review-label">
                          Reviewing a previous question · no automatic scoring
                        </p>
                      )}
                      <h2>{q.question}</h2>
                      {q.image && (
                        <ImageView
                          src={q.image}
                          onEnlarge={() => setImageOpen(true)}
                        />
                      )}
                      {!p.revealed && (
                        <p className="answer-prompt">
                          Discuss together. Answer aloud.
                        </p>
                      )}
                      {!audience && (
                        <div className="private-controls">
                          <p className="eyebrow">PRIVATE MARKING CONTROLS</p>
                          <details className="private-answer" key={q.id}>
                            <summary>View private answer</summary>
                            <p>{q.answer}</p>
                          </details>
                          {!r && s.current === q.id ? (
                            <>
                              <label htmlFor="answering-team">
                                Answering / receiving team · private
                                <select
                                  id="answering-team"
                                  value={team}
                                  disabled={disabled}
                                  onChange={(e) => setTeam(e.target.value)}
                                >
                                  <option value="">Choose a team…</option>
                                  {s.teams
                                    .filter(
                                      (t) => t.participating && !t.eliminated,
                                    )
                                    .map((t) => (
                                      <option key={t.id} value={t.id}>
                                        {t.name}
                                      </option>
                                    ))}
                                </select>
                              </label>
                              <label htmlFor="award-marks">
                                Marks to award · private
                                <input
                                  id="award-marks"
                                  type="number"
                                  min={1}
                                  max={1000}
                                  step={1}
                                  value={marks}
                                  disabled={disabled}
                                  onChange={(e) => setMarks(e.target.value)}
                                />
                              </label>
                              <div className="actions">
                                <button
                                  className="primary"
                                  disabled={
                                    disabled ||
                                    !team ||
                                    s.status !== "live" ||
                                    !(
                                      Number(marks) >= 1 &&
                                      Number(marks) <= 1000
                                    )
                                  }
                                  onClick={() =>
                                    act("judge", {
                                      question: q.id,
                                      outcome: "correct",
                                      team: Number(team),
                                      points: Number(marks),
                                    })
                                  }
                                >
                                  ★ Reward {Number(marks) || 0} marks
                                </button>
                                <button
                                  disabled={
                                    disabled || !team || s.status !== "live"
                                  }
                                  onClick={() =>
                                    act("judge", {
                                      question: q.id,
                                      outcome: "incorrect",
                                      team: Number(team),
                                    })
                                  }
                                >
                                  ✕ Incorrect · 0
                                </button>
                                <button
                                  disabled={disabled || s.status !== "live"}
                                  onClick={() =>
                                    act("judge", {
                                      question: q.id,
                                      outcome: "skip",
                                      team: Number(team),
                                    })
                                  }
                                >
                                  No answer / skip · 0
                                </button>
                              </div>
                            </>
                          ) : (
                            r && (
                              <div className="judgment">
                                <b>
                                  Recorded:{" "}
                                  {r.outcome === "skip"
                                    ? "No answer / skip"
                                    : r.outcome}
                                </b>
                                <p>
                                  {r.team
                                    ? s.teams.find((t) => t.id === r.team)
                                        ?.name + " · "
                                    : ""}
                                  {r.points} points
                                </p>
                                <button
                                  disabled={disabled}
                                  onClick={() => setCorrection(q.id)}
                                >
                                  Correct score / undo
                                </button>
                              </div>
                            )
                          )}
                          <div className="actions">
                            {r &&
                              button(
                                p.revealed
                                  ? "Hide audience answer"
                                  : "Reveal answer to audience",
                                "reveal",
                                { revealed: !p.revealed },
                              )}
                            {q.image &&
                              button(
                                p.imageOpen
                                  ? "Close projected image"
                                  : "Enlarge projected image",
                                "image",
                                { open: !p.imageOpen },
                              )}
                          </div>
                          <div className="actions navigation">
                            {previous &&
                              button("← Previous question", "review", {
                                question: previous,
                              })}
                            {r &&
                              s.current === q.id &&
                              button("Next question →", "next", {}, "primary")}
                            {s.current &&
                              s.current !== q.id &&
                              button(
                                "Return to current question",
                                "screen",
                                { screen: "question" },
                                "primary",
                              )}
                          </div>
                        </div>
                      )}
                    </article>
                  ) : (
                    <section className="card selection-board">
                      <p className="eyebrow">
                        {p.screen === "numbers"
                          ? "CHOOSE YOUR NUMBER"
                          : "CHOOSE YOUR CATEGORY"}
                      </p>
                      <h2>
                        {p.screen === "numbers"
                          ? categories.find(
                              ([slug]) => slug === p.category,
                            )?.[1]
                          : "What will you explore?"}
                      </h2>
                      {p.screen === "numbers" ? (
                        <>
                          <div className="number-grid">
                            {s.questions
                              .filter((q) => q.category === p.category)
                              .map((q) => {
                                const used = s.used.includes(q.id);
                                return audience ? (
                                  <div
                                    className={
                                      "number-tile " + (used ? "used" : "")
                                    }
                                    key={q.id}
                                  >
                                    <b>{String(q.number).padStart(2, "0")}</b>
                                    <small>
                                      {used ? "✓ Used" : "Available"}
                                    </small>
                                  </div>
                                ) : (
                                  <button
                                    className={
                                      "number-tile " + (used ? "used" : "")
                                    }
                                    key={q.id}
                                    disabled={
                                      disabled ||
                                      used ||
                                      s.status !== "live" ||
                                      !!s.current
                                    }
                                    onClick={() =>
                                      act("pick", { question: q.id })
                                    }
                                  >
                                    <b>{String(q.number).padStart(2, "0")}</b>
                                    <small>
                                      {used ? "✓ Used" : "Available"}
                                    </small>
                                  </button>
                                );
                              })}
                          </div>
                          {!audience && s.current && (
                            <p className="notice">
                              Finish the current question first. Use Current
                              question above, then judge or skip and choose Next
                              question.
                            </p>
                          )}
                        </>
                      ) : (
                        <div className="category-grid">
                          {categories.map(([slug, name], i) => {
                            const remaining = s.questions.filter(
                              (q) =>
                                q.category === slug && !s.used.includes(q.id),
                            ).length;
                            const content = (
                              <>
                                <span className="category-number">
                                  {String(i + 1).padStart(2, "0")}
                                </span>
                                <b>{name}</b>
                                <small>{remaining} available</small>
                              </>
                            );
                            return audience ? (
                              <div className="category-tile" key={slug}>
                                {content}
                              </div>
                            ) : (
                              <button
                                className="category-tile"
                                key={slug}
                                disabled={disabled}
                                onClick={() =>
                                  act("category", { category: slug })
                                }
                              >
                                {content}
                              </button>
                            );
                          })}
                        </div>
                      )}
                    </section>
                  )}
                </section>
                {p.screen !== "scoreboard" && <Scoreboard s={s} compact />}
              </div>
            )}
            {!audience && (
              <>
                <section className="card team-management">
                  <div className="section-heading">
                    <div>
                      <p className="eyebrow">COMPETITION TEAMS</p>
                      <h2>Participation & scores</h2>
                    </div>
                    <span className="muted">
                      Elimination keeps all points and history.
                    </span>
                  </div>
                  <div className="team-group">
                    <h3>Competing teams</h3>
                    <div className="team-grid">
                      {s.teams.filter((t) => !t.eliminated).map(teamRow)}
                    </div>
                  </div>
                  {eliminated.length > 0 && (
                    <div className="team-group eliminated-group">
                      <h3>Eliminated teams</h3>
                      <p className="muted">
                        Hidden from the audience screen. Points and history are
                        retained.
                      </p>
                      <div className="team-grid">
                        {eliminated.map(teamRow)}
                      </div>
                    </div>
                  )}
                </section>
                {correction && (
                  <section className="card" id="correction-panel">
                    <Correction
                      key={correction}
                      s={s}
                      qid={correction}
                      busy={disabled}
                      onSave={act}
                      onCancel={() => setCorrection(null)}
                    />
                  </section>
                )}
                <section className="card history">
                  <p className="eyebrow">SAVED ROUND HISTORY</p>
                  <h2>Review & correct</h2>
                  <p>
                    Reviewing changes the presentation only. Corrections replace
                    the recorded award and retain an audit trail.
                  </p>
                  {!s.history.length && <p>No questions opened yet.</p>}
                  {s.history.map((qid, i) => {
                    const q = s.questions.find((q) => q.id === qid),
                      result = s.results[qid];
                    return (
                      <div className="history-row" key={qid}>
                        <div>
                          <b>
                            {i + 1}. {q.categoryName} #{q.number}
                          </b>
                          <small>
                            {result
                              ? `${result.outcome} · ${result.points} points · ${s.teams.find((t) => t.id === result.team)?.name || "No team"}`
                              : "Awaiting judgment"}
                          </small>
                        </div>
                        <div className="actions">
                          {button("Review", "review", { question: qid })}
                          {result && (
                            <button
                              disabled={disabled}
                              onClick={() => setCorrection(qid)}
                            >
                              Correct score
                            </button>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </section>
                <details className="card audit">
                  <summary>Audit trail · latest 100 actions</summary>
                  {data.audit?.map((entry) => (
                    <div className="audit-row" key={entry.id}>
                      <b>
                        {entry.action.replaceAll("_", " ")} · {entry.actor}
                      </b>
                      <small>
                        {entry.created_at} · revision{" "}
                        {entry.details.revision || 0}
                      </small>
                      {entry.details.after && (
                        <p>
                          {entry.details.before
                            ? `${entry.details.before.outcome} / ${s.teams.find((t) => t.id === entry.details.before.team)?.name || "No team"} / ${entry.details.before.points} points → `
                            : ""}
                          {entry.details.after.outcome} /{" "}
                          {s.teams.find(
                            (t) => t.id === entry.details.after.team,
                          )?.name || "No team"}{" "}
                          / {entry.details.after.points} points
                          {entry.details.input?.reason
                            ? " · " + entry.details.input.reason
                            : ""}
                        </p>
                      )}
                    </div>
                  ))}
                </details>
              </>
            )}
            <ImageDialog
              src={q?.image}
              open={imageOpen && !!q?.image}
              onClose={closeImage}
            />
            <AnswerDialog
              open={Boolean(p.revealed && q && p.screen === "question")}
              answer={q?.answer}
              heading={
                q
                  ? `${q.categoryName} / Question ${String(q.number).padStart(2, "0")}`
                  : "The answer"
              }
              dismissible={!audience}
              onClose={() => act("reveal", { revealed: false })}
            />
          </>
        )}
      </main>
      <footer>
        THE QUIZ <span>Think together. Win together.</span>
      </footer>
    </>
  );
}
createRoot(document.getElementById("root")).render(<App />);
