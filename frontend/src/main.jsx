import React, { useEffect, useRef, useState } from "react";
import { createRoot } from "react-dom/client";
import "./style.css";
const root = import.meta.env.DEV ? "/" : "../";
const viewToken = new URLSearchParams(location.search).get("view");
const storageKey = "the-quiz-session:" + location.pathname;
async function request(action, { id, token, body, csrf } = {}) {
  const url = new URL(root + "api/index.php", location.href);
  url.searchParams.set("action", action);
  if (id) url.searchParams.set("id", id);
  if (token) url.searchParams.set("token", token);
  const res = await fetch(url, {
    credentials: "same-origin",
    headers: body
      ? { "Content-Type": "application/json", "X-CSRF-Token": csrf }
      : undefined,
    method: body ? "POST" : "GET",
    body: body ? JSON.stringify(body) : undefined,
  });
  const data = await res.json();
  if (!res.ok) {
    const e = new Error(data.error || "Request failed");
    e.status = res.status;
    throw e;
  }
  return data;
}
function App() {
  const [boot, setBoot] = useState(null),
    [id, setId] = useState(() =>
      viewToken ? null : localStorage.getItem(storageKey),
    ),
    [data, setData] = useState(null),
    [error, setError] = useState(""),
    [busy, setBusy] = useState(false),
    [category, setCategory] = useState(""),
    [title, setTitle] = useState("School Quiz"),
    [names, setNames] = useState("Team A\nTeam B\nTeam C"),
    [login, setLogin] = useState(false);
  const lock = useRef(false),
    epoch = useRef(0);
  const s = data?.session,
    r = s?.round,
    q = s?.questions.find((q) => q.id === r?.question);
  const name = (tid) => s?.teams.find((t) => t.id === tid)?.name;
  const fail = (e) => {
    setError(e.message);
    if (e.status === 401) setLogin(true);
  };
  const refresh = async () => {
    const ticket = ++epoch.current;
    const d = await request(viewToken ? "view" : "session", {
      id,
      token: viewToken,
    });
    if (ticket === epoch.current) setData(d);
  };
  useEffect(() => {
    if (!viewToken) request("bootstrap").then(setBoot).catch(fail);
  }, []);
  useEffect(() => {
    if (!viewToken) return;
    document.body.classList.add("audience-screen");
    return () => document.body.classList.remove("audience-screen");
  }, []);
  useEffect(() => {
    setData(null);
    setCategory("");
    if (!id && !viewToken) return;
    let active = true;
    const poll = async () => {
      if (lock.current) return;
      const ticket = ++epoch.current;
      try {
        const d = await request(viewToken ? "view" : "session", {
          id,
          token: viewToken,
        });
        if (active && ticket === epoch.current) {
          setData(d);
          setError("");
        }
      } catch (e) {
        if (active) fail(e);
      }
    };
    poll();
    const interval = setInterval(poll, 2500);
    return () => {
      active = false;
      clearInterval(interval);
      ++epoch.current;
    };
  }, [id]);
  const act = async (action, extra = {}) => {
    if (lock.current) return;
    lock.current = true;
    setBusy(true);
    setError("");
    ++epoch.current;
    try {
      const d = await request(action, {
        id,
        csrf: boot.csrf,
        body: { revision: data.revision, ...extra },
      });
      setData(d);
    } catch (e) {
      fail(e);
      if (e.status === 409) await refresh().catch(fail);
    } finally {
      lock.current = false;
      setBusy(false);
    }
  };
  const open = (next) => {
    localStorage.setItem(storageKey, next);
    setId(String(next));
  };
  const create = async (e) => {
    e.preventDefault();
    if (lock.current) return;
    lock.current = true;
    setBusy(true);
    setError("");
    try {
      const d = await request("create", {
        csrf: boot.csrf,
        body: {
          title,
          teams: names
            .split("\n")
            .map((n) => n.trim())
            .filter(Boolean),
        },
      });
      open(d.id);
    } catch (e) {
      fail(e);
    } finally {
      lock.current = false;
      setBusy(false);
    }
  };
  const home = () => {
    localStorage.removeItem(storageKey);
    setId(null);
    setData(null);
    request("bootstrap").then(setBoot).catch(fail);
  };
  const removeSession = async (sessionId, sessionTitle) => {
    if (
      lock.current ||
      !confirm(
        `Delete "${sessionTitle}"? This session and all of its scores will be permanently deleted.`,
      )
    )
      return;
    lock.current = true;
    setBusy(true);
    setError("");
    try {
      await request("delete", { id: sessionId, csrf: boot.csrf, body: {} });
      if (String(sessionId) === String(id)) {
        localStorage.removeItem(storageKey);
        setId(null);
        setData(null);
      }
      setBoot(await request("bootstrap"));
    } catch (e) {
      fail(e);
    } finally {
      lock.current = false;
      setBusy(false);
    }
  };
  const button = (label, action, extra = {}, cls = "") => (
    <button
      className={cls}
      disabled={busy || !boot}
      onClick={() => act(action, extra)}
    >
      {label}
    </button>
  );
  const categories = [...new Set(s?.questions.map((q) => q.category) || [])];
  const ranks = [...(s?.teams || [])].sort(
    (a, b) => b.score - a.score || a.id - b.id,
  );
  const winnerTeam = r?.winner ? s?.teams.find((t) => t.id === r.winner) : null;
  return (
    <>
      <header>
        <a className="brand" href="./">
          <span className="brand-icon">Q</span> THE QUIZ <small>LIVE</small>
        </a>
        <nav>
          {!viewToken && (
            <>
              <button className="quiet" onClick={home}>
                Sessions
              </button>
              <a href={root + "dashboard.php"}>Workspace ↗</a>
            </>
          )}
          <button
            className="quiet"
            onClick={() => {
              (document.fullscreenElement
                ? document.exitFullscreen()
                : document.documentElement.requestFullscreen()
              ).catch(fail);
            }}
          >
            Fullscreen
          </button>
        </nav>
      </header>
      <main>
        {error && (
          <div className="error" role="alert">
            {error}
          </div>
        )}
        {login ? (
          <section className="welcome">
            <h1>Welcome, quiz host.</h1>
            <p>Sign in with your existing admin account to host a session.</p>
            <a className="button primary" href={root + "login.php"}>
              Admin sign in →
            </a>
            <p>After signing in, open the Live quiz link on your dashboard.</p>
          </section>
        ) : !s ? (
          viewToken || id ? (
            <section className="welcome">
              <h1>{error ? "Unable to load session" : "Loading your quiz…"}</h1>
              {!viewToken && <button onClick={home}>Back to sessions</button>}
            </section>
          ) : (
            <section className="setup">
              <div>
                <p className="eyebrow">BRING EVERYONE TOGETHER</p>
                <h1>
                  Big questions.
                  <br />
                  <em>Team spirit.</em>
                </h1>
                <p className="intro">
                  Pick a number. Share your answer.
                  <br />
                  Let the best team win.
                </p>
                <div className="rules">
                  <span>
                    Correct <b>+10</b>
                  </span>
                  <span>
                    Steal <b>+5</b>
                  </span>
                  <span>
                    Wrong <b>0</b>
                  </span>
                </div>
                <h3>Resume a session</h3>
                <div className="session-list">
                  {boot?.sessions.length === 0 && (
                    <p>Your sessions will appear here.</p>
                  )}
                  {boot?.sessions.map((x) => (
                    <div className="session-row" key={x.id}>
                      <button onClick={() => open(x.id)}>{x.title}<span>Open →</span></button>
                      <button className="delete-session" disabled={busy} aria-label={`Delete ${x.title}`} onClick={() => removeSession(x.id, x.title)}>Delete</button>
                    </div>
                  ))}
                </div>
              </div>
              <form className="card" onSubmit={create}>
                <p className="eyebrow">HOST DESK</p>
                <h2>Start a new session</h2>
                <label>
                  Session name
                  <input
                    required
                    maxLength={120}
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                  />
                </label>
                <label>
                  Teams <small>One name per line · 2–12 teams</small>
                  <textarea
                    required
                    rows={5}
                    value={names}
                    onChange={(e) => setNames(e.target.value)}
                  />
                </label>
                <p>Each session starts with fresh questions and zero scores.</p>
                <button className="primary" disabled={busy || !boot}>
                  {busy ? "Creating…" : "Let’s play →"}
                </button>
              </form>
            </section>
          )
        ) : (
          <>
            <div className="session-heading">
              <div>
                <p className="eyebrow">
                  {s.ended
                    ? "FINAL RESULTS"
                    : viewToken
                      ? "AUDIENCE SCREEN"
                      : "HOST DESK"}
                </p>
                <h1>{s.title}</h1>
              </div>
              <div className="heading-actions">
                <span className="pill">
                  {s.used.length} / {s.questions.length} played
                </span>
                {!viewToken && (
                  <>
                  <a
                    className="button"
                    target="_blank"
                    rel="noreferrer"
                    href={root + "live/?view=" + data.viewerToken}
                  >
                    Open live screen ↗
                  </a>
                  <button className="delete-session" disabled={busy} onClick={() => removeSession(id, s.title)}>Delete session</button>
                  </>
                )}
              </div>
            </div>
            <div className="game-layout">
              <section className="board">
                {s.ended ? (
                  <div className="question card final">
                    <p className="eyebrow">
                      {ranks.filter((t) => t.score === ranks[0].score).length >
                      1
                        ? "IT’S A TIE"
                        : "THE WINNER"}
                    </p>
                    <h1>
                      {ranks
                        .filter((t) => t.score === ranks[0].score)
                        .map((t) => t.name)
                        .join(" & ")}
                    </h1>
                    <p>{ranks[0].score} points</p>
                    {!viewToken && (
                      <button className="primary" onClick={home}>
                        Back to sessions
                      </button>
                    )}
                  </div>
                ) : r ? (
                  <div className="card question">
                    <div
                      className="question-progress"
                      aria-label="Round progress"
                    >
                      <span>
                        <b>1</b>Choose
                      </span>
                      <i>—</i>
                      <span className={!r.revealed ? "current" : ""}>
                        <b>2</b>Answer
                      </span>
                      <i>—</i>
                      <span className={r.revealed ? "current" : ""}>
                        <b>3</b>Reveal
                      </span>
                    </div>
                    <div className="question-meta">
                      <span>
                        {q.category} / {String(q.number).padStart(2, "0")}
                      </span>
                      <span>
                        {r.stage === "stealing"
                          ? "Steal · " + name(r.stealTeam)
                          : name(r.team)}
                      </span>
                    </div>
                    <h2>{q.question}</h2>
                    {q.image && (
                      <img
                        className="question-image"
                        src={root + q.image}
                        alt="Question illustration"
                        onError={(e) => {
                          e.currentTarget.style.display = "none";
                        }}
                      />
                    )}
                    {r.revealed ? (
                      <div className="answer">
                        <p className="eyebrow">THE ANSWER</p>
                        <h3>{q.answer}</h3>
                      </div>
                    ) : (
                      <p className="muted">
                        {r.stage === "wrong"
                          ? "Answer incorrect. The host can offer a steal."
                          : r.stage === "judged"
                            ? "Answer judged. Ready for the reveal."
                            : "Discuss with your team and answer aloud."}
                      </p>
                    )}
                    {winnerTeam && (
                      <div className="award-banner" role="status" aria-live="polite">
                        <span className="award-icon" aria-hidden="true">★</span>
                        <div><p><strong>{winnerTeam.name} wins {r.points} marks!</strong></p><small>Added to their bank</small></div>
                        <b className="bank-total">{winnerTeam.score}<small>bank total</small></b>
                      </div>
                    )}
                    {!viewToken && (
                      <div className="controls">
                        {["answering", "stealing"].includes(r.stage) && (
                          <>
                            {button("✓ Correct", "correct", {}, "primary")}
                            {button("✕ Wrong", "wrong")}
                          </>
                        )}
                        {r.stage === "wrong" && (
                          <label>
                            Offer one steal
                            <select
                              defaultValue=""
                              disabled={busy}
                              onChange={(e) =>
                                e.target.value &&
                                act("pass", { team: Number(e.target.value) })
                              }
                            >
                              <option value="">Choose another team…</option>
                              {s.teams
                                .filter((t) => t.id !== r.team)
                                .map((t) => (
                                  <option key={t.id} value={t.id}>
                                    {t.name}
                                  </option>
                                ))}
                            </select>
                          </label>
                        )}
                        {["wrong", "judged"].includes(r.stage) &&
                          !r.revealed &&
                          button("Reveal answer", "reveal", {}, "primary")}
                        {r.revealed &&
                          button("Next question →", "finish", {}, "primary")}
                        {button(
                          r.revealed ? "Cancel these points" : "Undo judgment",
                          "undo",
                          {},
                          "quiet",
                        )}
                      </div>
                    )}
                  </div>
                ) : (
                  <>
                    <div className="turn">
                      <span className="dot" />
                      <span>
                        Up next: <b>{name(s.activeTeam)}</b>
                      </span>
                      {!viewToken && (
                        <select
                          aria-label="Active team"
                          value={s.activeTeam}
                          disabled={busy}
                          onChange={(e) =>
                            act("select_team", { team: Number(e.target.value) })
                          }
                        >
                          {s.teams.map((t) => (
                            <option key={t.id} value={t.id}>
                              {t.name}
                            </option>
                          ))}
                        </select>
                      )}
                    </div>
                    <h2>Choose your category</h2>
                    <div className="categories">
                      {categories.map((c, i) => (
                        <button
                          className={category === c ? "selected" : ""}
                          key={c}
                          onClick={() => setCategory(c)}
                        >
                          <span className="category-icon">
                            {["◈", "✦", "◎", "♫", "✧", "◇", "✺"][i % 7]}
                          </span>
                          <b>{c}</b>
                          <small>
                            {
                              s.questions.filter(
                                (q) =>
                                  q.category === c && !s.used.includes(q.id),
                              ).length
                            }{" "}
                            remaining
                          </small>
                        </button>
                      ))}
                    </div>
                    {category && (
                      <div className="card numbers">
                        <h3>Pick a question · {category}</h3>
                        <div className="number-grid">
                          {s.questions
                            .filter((q) => q.category === category)
                            .map((q) => (
                              <button
                                key={q.id}
                                disabled={
                                  viewToken || busy || s.used.includes(q.id)
                                }
                                className={s.used.includes(q.id) ? "used" : ""}
                                onClick={() => act("pick", { question: q.id })}
                              >
                                {s.used.includes(q.id)
                                  ? "✓"
                                  : String(q.number).padStart(2, "0")}
                              </button>
                            ))}
                        </div>
                        {viewToken && (
                          <p>The host will open the selected question.</p>
                        )}
                      </div>
                    )}
                    {!viewToken && (
                      <div className="bottom-controls">
                        <button
                          className="quiet"
                          disabled={busy || !s.history.length}
                          onClick={() => {
                            if (
                              confirm(
                                "Cancel points for the most recently completed question? It will remain used.",
                              )
                            )
                              act("undo");
                          }}
                        >
                          Undo last score
                        </button>
                        <button
                          disabled={busy}
                          onClick={() => {
                            if (
                              confirm(
                                "End this session and show the final results?",
                              )
                            )
                              act("end");
                          }}
                        >
                          End session
                        </button>
                      </div>
                    )}
                  </>
                )}
              </section>
              <aside className="card leaderboard">
                <p className="eyebrow">TEAM BANKS</p>
                <h2>Leaderboard</h2>
                {ranks.map((t, i) => (
                  <div
                    className={
                      "team " + (s.activeTeam === t.id ? "active " : "") + (winnerTeam?.id === t.id ? "just-awarded" : "")
                    }
                    key={t.id}
                  >
                    <span className="rank">
                      {ranks.findIndex((x) => x.score === t.score) + 1}
                    </span>
                    <div>
                      <b>{t.name}</b>
                      <small>
                          {winnerTeam?.id === t.id
                            ? `+${r.points} marks added`
                            : !s.ended && s.activeTeam === t.id
                          ? "Current turn"
                          : "Team " + t.id}
                      </small>
                    </div>
                      <strong aria-label={`${t.score} marks in bank`}>{t.score}<small>marks</small></strong>
                  </div>
                ))}
                <div className="score-key">
                  <span>
                    Correct answer <b>+10</b>
                  </span>
                  <span>
                    Successful steal <b>+5</b>
                  </span>
                  <span>
                    Wrong answer <b>0</b>
                  </span>
                </div>
                <p className="muted">
                  {viewToken ? "Team banks update automatically." : "Marks are saved in each team bank."}
                </p>
              </aside>
            </div>
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
