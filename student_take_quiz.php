<?php
// student_take_quiz.php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
  header('Location: login.php');
  exit;
}

if (!isset($_GET['quiz_id']) || !is_numeric($_GET['quiz_id'])) {
  die('Quiz not specified.');
}
$quiz_id = (int)$_GET['quiz_id'];

// fetch quiz
$quiz_q = $conn->prepare("SELECT id, title, description, time_limit FROM quizzes WHERE id = ?");
$quiz_q->bind_param("i", $quiz_id);
$quiz_q->execute();
$quiz = $quiz_q->get_result()->fetch_assoc();

// fetch questions (raw from DB)
$q_q = $conn->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
$q_q->bind_param("i", $quiz_id);
$q_q->execute();
$res = $q_q->get_result();
$questions = [];
while ($r = $res->fetch_assoc()) {
    $questions[] = $r;
}
//random question
foreach ($questions as $idx => $q) {
    $origOptions = [
        'A' => $q['option_a'],
        'B' => $q['option_b'],
        'C' => $q['option_c'],
        'D' => $q['option_d'],
    ];
    $optList = [];
    foreach ($origOptions as $letter => $text) {
        if ($text !== null && trim($text) !== '') {
            $optList[] = ['orig' => $letter, 'text' => $text];
        }
    }
    if (count($optList) > 1) shuffle($optList);
    $displayLetters = ['A','B','C','D'];
    $mapDisplayToOriginal = [];
    $newOptions = ['option_a'=>'','option_b'=>'','option_c'=>'','option_d'=>''];
    for ($i = 0; $i < count($optList); $i++) {
        $disp = $displayLetters[$i];
        $newOptions['option_' . strtolower($disp)] = $optList[$i]['text'];
        $mapDisplayToOriginal[$disp] = $optList[$i]['orig'];
    }
    for ($i = count($optList); $i < 4; $i++) {
        $disp = $displayLetters[$i];
        $newOptions['option_' . strtolower($disp)] = '';
    }
    $questions[$idx]['option_a'] = $newOptions['option_a'];
    $questions[$idx]['option_b'] = $newOptions['option_b'];
    $questions[$idx]['option_c'] = $newOptions['option_c'];
    $questions[$idx]['option_d'] = $newOptions['option_d'];
    $questions[$idx]['option_map'] = $mapDisplayToOriginal;
}

if (count($questions) > 1) {
    shuffle($questions);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title><?php echo htmlspecialchars($quiz['title']); ?> - Take Quiz</title>
<meta name="viewport" content="width=device-width,initial-scale=1">

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
  /* Core layout + UI (kept consistent with your previous theme) */
  body{
    font-family:'Poppins',sans-serif;
    background:#f3f4f6;
    margin:0;
    color:#111;}
  .navbar{
    background:#2563eb;
    color:white;
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:15px 25px;
    font-weight:600;}
  .navbar img{
    height:38px;
    margin-right:8px;}
  .page{
    max-width:1200px;
    margin:30px auto;
    display:flex;
    gap:24px;
    padding:0 20px;}
  .quiz-panel{
    flex:1;
    background:white;
    border-radius:14px;
    box-shadow:0 8px 20px rgba(0,0,0,0.06);
    padding:28px;}
  .quiz-title{
    color:#1e3a8a;
    border-bottom:2px solid #2563eb;display:inline-block;
    padding-bottom:4px;
    margin:0 0 10px;}
  .qcard{
    margin-top:20px;
    background:#f9fafb;
    padding:22px;
    border-radius:10px;
    box-shadow:0 4px 12px rgba(0,0,0,0.03);}
  .qtext{
    font-weight:700;
    margin:0 0 10px;}
  .question-image{
    max-width:320px;
    margin:12px 0;
    border-radius:8px;}
  .options{
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    margin-top:10px;}
  input[type=radio]{
    display:none;}
  .option-label{
    background:#fff;
    border:2px solid #e5e7eb;
    border-radius:10px;
    padding:12px 16px;
    flex:1 1 calc(50% - 12px);
    cursor:pointer;
    font-weight:600;}
  input[type=radio]:checked + label{
    background:#2563eb;
    color:#fff;
    border-color:#2563eb;}
  #timer-box{
    position:fixed;
    top:15px;
    right:25px;
    background:#2563eb;
    color:white;
    padding:10px 18px;
    border-radius:12px;
    font-weight:bold;
    font-size:16px;
    display:none;
    z-index:1200;}

  .side{
    width:260px;
    background:white;
    border-radius:12px;
    padding:16px;
    box-shadow:0 6px 18px rgba(0,0,0,0.06);}
  .qnav{
    display:flex;
    flex-wrap:wrap;
    gap:10px;
    padding-top:6px;}
  .qnav button{
    border:none;
    padding:8px;
    border-radius:8px;
    font-weight:700;
    cursor:pointer;
    height:44px;
    min-width:44px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    box-shadow:0 6px 14px rgba(0,0,0,0.04);}

  .status-not-visited{
    background:#fff7f7;
    color:#b91c1c;
    border:1px solid #fca5a5;}
  .status-visited{
    background:#fffaf0;
    color:#92400e;
    border:1px solid #fcd34d;}
  .status-answered{
    background:#f0fdf4;
    color:#065f46;
    border:1px solid #34d399;}

  /* Instructions overlay */
  #start-screen{
    position:fixed;
    inset:0;
    background:#ffffffee;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    z-index:999;
    padding:20px;
  }
  .instr-box{
    width:min(900px,96%);
    background:white;
    border-radius:12px;
    padding:22px 26px;
    box-shadow:0 10px 30px rgba(15,23,42,0.07);
  }
  .instr-title{
    font-size:20px;
    color:#0f172a;
    font-weight:700;
    margin:6px 0 12px;}
  .instr-list{
    color:#374151;
    font-size:15px;
    line-height:1.6;
    margin-bottom:14px;}
  .instr-actions{
    display:flex;
    gap:12px;
    align-items:center;
    justify-content:flex-end;
    margin-top:14px;}
  #start-btn{
    background:#2563eb;
    color:white;
    padding:12px 18px;
    font-size:16px;
    border:none;
    border-radius:8px;
    cursor:pointer;}
  #start-btn:disabled{
    opacity:0.6;
    cursor:not-allowed;}

  #submitting-overlay{
    position:fixed;
    inset:0;
    background:#00000066;
    display:none;
    align-items:center;
    justify-content:center;
    z-index:1500;
    color:white;
    font-weight:700;}

  /* progress */
  #progress-container{
    background:#e5e7eb;
    border-radius:8px;
    height:14px;
    margin:10px 0;
    position:relative;}
  #progress-bar{
    height:100%;
    width:0;
    background:#2563eb;
    transition:.4s;}
  #progress-text{
    position:absolute;
    top:-22px;
    right:0;
    font-size:13px;
    font-weight:600;
    color:#444;}

  /* toast */
  #qc-toast{
    position:fixed;
    right:20px;
    bottom:20px;
    min-width:260px;
    max-width:320px;
    background:#000c;
    color:#fff;
    padding:12px;
    border-radius:10px;
    display:none;
    z-index:9999;}
  #qc-toast.warning{
    background:#f59e0b;
    color:#111;}
  #qc-toast.danger{
    background:#ef4444;
    color:#fff;}

  /* footer buttons */
  .btn { 
    background: #2563eb; 
    color: #fff; 
    border: none; 
    border-radius: 8px; 
    padding: 8px 14px; 
    font-weight: 600; 
    cursor: pointer; 
    min-height: 36px; 
    line-height: 1; 
    box-shadow: 0 6px 12px rgba(37,99,235,0.12); 
    display: inline-flex; 
    align-items: center; 
    gap: 8px; }
  .btn.secondary { 
    background: #6b7280; 
    box-shadow: 0 4px 8px rgba(107,114,128,0.08); }
  .quiz-footer .btn { 
    margin-left: 6px; }

  @media (max-width:900px){ .side { width: 100%; order: 2; } .page{flex-direction:column;} }
</style>
</head>
<body>

<div class="navbar">
  <div><img src="css/Quiz Campus  logo.png" alt="logo"> Quiz Campus - Student</div>
  <a href="logout.php" style="color:white;">Logout</a>
</div>

<div id="timer-box">⏱ <span id="timer">00:00</span></div>

<!-- START / INSTRUCTIONS SCREEN -->
<div id="start-screen" aria-hidden="false">
  <div class="instr-box" role="dialog" aria-labelledby="instr-title">
    <div style="display:flex;justify-content:space-between;align-items:center">
      <div>
        <div id="instr-title" class="instr-title"><?php echo htmlspecialchars($quiz['title']); ?> — Instructions</div>
        <div style="color:#6b7280;font-size:13px;margin-top:4px;"><?php echo htmlspecialchars($quiz['description']); ?></div>
      </div>
      <div style="text-align:right;color:#374151;font-weight:700;">
        <?php $minutes = ((int)$quiz['time_limit'] ?: 10); echo htmlspecialchars($minutes) . " min"; ?>
      </div>
    </div>

    <div class="instr-list">
      <ul style="margin:12px 0 0 18px;">
        <li><strong>Time:</strong> You have <?php echo htmlspecialchars($minutes); ?> minutes for this quiz (set by your teacher).</li>
        <li><strong>Timer:</strong> Once started, the timer cannot be paused.</li>
        <li><strong>Do not refresh or close the tab:</strong> doing so may auto-submit your quiz and count as a tab-switch.</li>
        <li><strong>Full-screen:</strong> Quiz may request full-screen mode; remain on a single tab for the duration.</li>
      </ul>
    </div>

    <div style="display:flex;align-items:center;gap:12px;margin-top:16px;">
      <input type="checkbox" id="instrAgree" />
      <label for="instrAgree" style="color:#374151;"> I have read the instructions</label>
    </div>

    <div class="instr-actions">
      <button id="start-btn" class="btn" disabled>Start Quiz</button>
    </div>
  </div>
</div>

<div id="submitting-overlay" aria-hidden="true">
  <div style="background:#ffffff22;padding:20px 30px;border-radius:10px;">
    <div style="font-size:22px;margin-bottom:8px;">Submitting quiz…</div>
    <div style="font-size:14px;">Please wait…</div>
  </div>
</div>

<div id="qc-toast" aria-live="polite"><div class="msg"></div></div>

<div class="page" aria-hidden="false">
  <div class="quiz-panel" aria-live="polite">
    <h2 class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></h2>

    <div id="progress-container">
      <div id="progress-bar"></div>
      <span id="progress-text">0% completed</span>
    </div>

    <p><?php echo htmlspecialchars($quiz['description']); ?></p>

    <form id="quiz-form" action="submit_quiz.php" method="post">
      <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">

      <!-- option_map hidden inputs for mapping displayed letters -> original DB letters -->
      <?php foreach ($questions as $q): ?>
        <input type="hidden" name="option_map[<?php echo (int)$q['id']; ?>]" value='<?php echo htmlspecialchars(json_encode($q['option_map'], JSON_HEX_APOS|JSON_HEX_QUOT)); ?>'>
      <?php endforeach; ?>

      <div id="question-area"></div>

      <div class="quiz-footer" style="display:flex;justify-content:space-between;margin-top:20px;">
        <div>Question <span id="current-index">1</span> of <span id="total-count"><?php echo count($questions); ?></span></div>

        <div style="display:flex;gap:10px;">
          <button type="button" id="prev-btn" class="btn secondary">◀ Previous</button>
          <button type="button" id="next-btn" class="btn">Next ▶</button>
          <button type="submit" id="submit-btn" class="btn">Submit Quiz</button>
        </div>
      </div>
    </form>
  </div>

  <div class="side" aria-hidden="false">
    <h4>Questions</h4>
    <div style="margin:10px 0 14px;color:#374151;font-size:13px;">
      <div style="display:flex;gap:10px;align-items:center;">
        <span style="width:14px;height:14px;border-radius:3px;background:#ecfdf5;display:inline-block;border:1px solid #34d399"></span>
        <span style="margin-right:16px;">Answered</span>
        <span style="width:14px;height:14px;border-radius:3px;background:#fff7ed;display:inline-block;border:1px solid #fcd34d"></span>
        <span style="margin-right:16px;">Visited / Skipped</span>
        <span style="width:14px;height:14px;border-radius:3px;background:#fff7f7;display:inline-block;border:1px solid #fca5a5"></span>
        <span>Not visited</span>
      </div>
    </div>

    <div id="qnav" class="qnav"></div>
  </div>
</div>

<script>

const QUESTIONS = <?php echo json_encode($questions); ?>;
const TOTAL = QUESTIONS.length;

const questionArea=document.getElementById('question-area');
const qnav=document.getElementById('qnav');
const timerBox=document.getElementById('timer-box');
const timerEl=document.getElementById('timer');
const startScreen=document.getElementById('start-screen');
const startBtn=document.getElementById('start-btn');
const instrAgree=document.getElementById('instrAgree');

const prevBtn=document.getElementById('prev-btn');
const nextBtn=document.getElementById('next-btn');
const currentIndexEl=document.getElementById('current-index');
const totalCountEl=document.getElementById('total-count');
const form=document.getElementById('quiz-form');
const submitBtn=document.getElementById('submit-btn');
const submittingOverlay=document.getElementById('submitting-overlay');
const progressBar=document.getElementById('progress-bar');
const progressText=document.getElementById('progress-text');

let current=0;
let answers={};
let status=Array(TOTAL).fill('not-visited');
let timeLimit=<?php echo (int)$quiz['time_limit'] ?: 10; ?>*60;
let countdown;
let quizStarted=false;
let isSubmitting=false;

/* Build UI from QUESTIONS (questions/options are already shuffled server-side) */
function buildUI(){
  questionArea.innerHTML=''; qnav.innerHTML='';
  QUESTIONS.forEach((q,i)=>{
    const wrap=document.createElement('div');
    wrap.className='qcard'; wrap.dataset.index=i; wrap.dataset.qid=q.id; wrap.style.display=i===0?'block':'none';

    let html=`<div style="display:flex;justify-content:space-between;align-items:center;">
      <p class="qtext">Q${i+1}. ${q.question_text}</p>
      <span style="font-weight:600;color:#6b7280;">Question ${i+1} of ${TOTAL}</span></div>`;

    if(q.image){ html+=`<img class="question-image" src="uploads/${q.image}" alt="question image">`; }

    html+=`<div class="options">`;
    ['A','B','C','D'].forEach(L=>{
      const optText = q['option_' + L.toLowerCase()];
      if(optText){
        const id = `q${q.id}_${L}`;
        html += `<input type="radio" id="${id}" name="answers[${q.id}]" value="${L}">
                <label for="${id}" class="option-label"><b>${L}.</b> ${optText}</label>`;
      }
    });
    html += `</div>`;

    wrap.innerHTML = html;
    questionArea.appendChild(wrap);

    const nb = document.createElement('button');
    nb.textContent = i+1;
    nb.dataset.index = i;
    nb.className = 'status-not-visited';
    nb.onclick = ()=> showQuestion(i);
    qnav.appendChild(nb);
  });
  totalCountEl.textContent=TOTAL; currentIndexEl.textContent=TOTAL>0?1:0;
  updateNav(); updateProgress();
}

function showQuestion(i){
  document.querySelectorAll('.qcard').forEach(c=>c.style.display='none');
  document.querySelector(`.qcard[data-index="${i}"]`).style.display='block';
  if(status[i]==='not-visited') status[i]='visited';
  current=i; currentIndexEl.textContent=i+1;
  const qid = QUESTIONS[i].id;
  if(answers[qid]){
    const input = document.querySelector(`#q${qid}_${answers[qid].toLowerCase()}`);
    if(input) input.checked = true;
  }
  attachOptionListeners(i); updateNav();
}

function attachOptionListeners(i){
  const qid = QUESTIONS[i].id;
  document.querySelectorAll(`input[name="answers[${qid}]"]`).forEach(inp=>{
    inp.onchange = function(){
      answers[qid] = this.value.toUpperCase();
      status[i] = 'answered';
      updateNav(); updateProgress();
    };
  });
}

function updateNav(){
  qnav.querySelectorAll('button').forEach((b,i)=>{
    b.className = 'status-' + (status[i]==='answered' ? 'answered' : status[i]==='visited' || status[i]==='skipped' ? 'visited' : 'not-visited');
  });
}

function updateProgress(){
  const answered = status.filter(s=>s==='answered').length;
  const percent = Math.round((answered/TOTAL)*100) || 0;
  progressBar.style.width = percent + '%';
  progressText.textContent = percent + '% completed';
  progressBar.style.background = percent < 50 ? '#dc2626' : percent < 80 ? '#f59e0b' : '#16a34a';
}

prevBtn.onclick = ()=> { if(current>0) showQuestion(current-1); };
nextBtn.onclick = ()=> { if(!answers[QUESTIONS[current].id]) status[current] = 'skipped'; if(current<TOTAL-1) showQuestion(current+1); };

/* ---------- Instructions / Start behavior ---------- */
instrAgree.addEventListener('change', function(){ startBtn.disabled = !this.checked; });

startBtn.onclick = ()=>{
  if(!instrAgree.checked) { alert("Please confirm that you have read the instructions."); return; }
  try{ if(typeof qc_startMonitoring === 'function') qc_startMonitoring(); } catch(e){}
  startScreen.style.display='none';
  timerBox.style.display='block';
  buildUI();
  showQuestion(0);
  startTimer();
  quizStarted = true;
};

/* beforeunload safeguard */
window.addEventListener('beforeunload', function(e){
  if(quizStarted && !isSubmitting){
    e.preventDefault(); e.returnValue = '';
    return '';
  }
});

/* Timer */
function startTimer(){
  countdown = setInterval(()=>{
    const m = Math.floor(timeLimit/60);
    const s = timeLimit % 60;
    timerEl.textContent = `${m}:${s<10?'0'+s:s}`;
    if(timeLimit <= 60) timerBox.style.background = '#dc2626';
    if(timeLimit <= 0){ clearInterval(countdown); showSubmittingAndSubmit(true); }
    timeLimit--;
  }, 1000);
}

/* Prepare answers hidden inputs */
function prepareAndAttachAnswers(){
  document.querySelectorAll('input.auto-ans').forEach(e=>e.remove());
  for(const [qid, val] of Object.entries(answers)){
    const inp = document.createElement('input');
    inp.type = 'hidden';
    inp.name = `answers[${qid}]`;
    inp.value = val;
    inp.className = 'auto-ans';
    form.appendChild(inp);
  }
}

/* submit handling */
function showSubmittingAndSubmit(auto=false){
  isSubmitting = true;
  submittingOverlay.style.display = 'flex';
  setTimeout(()=>{ prepareAndAttachAnswers(); form.submit(); }, 600);
}

form.addEventListener('submit', function(e){
  e.preventDefault();
  const answeredCount = status.filter(s=>s==='answered').length;
  Swal.fire({
    title: "Submit Quiz?",
    html: `You have answered <b>${answeredCount}</b> out of <b>${TOTAL}</b> questions.`,
    icon: "question",
    showCancelButton: true,
    confirmButtonColor: "#2563eb",
    cancelButtonColor: "#6b7280",
    confirmButtonText: "Yes, Submit",
    cancelButtonText: "Cancel"
  }).then((result)=>{
    if(result.isConfirmed){
      showSubmittingAndSubmit(false);
    }
  });
});

(function(){
  const QUIZ_FORM_ID = "quiz-form";
  const MAX_TAB_SWITCHES = 3;
  const MAX_FULLSCREEN_EXITS = 2;
  let tabSwitchCount = 0;
  let fsExitCount = 0;
  const toastEl = document.getElementById("qc-toast");

  function showToast(msg, type){
    const inner = toastEl.querySelector('.msg');
    if(inner){ inner.textContent = msg; } else { toastEl.textContent = msg; }
    toastEl.className = "";
    if(type==="warning") toastEl.classList.add("warning");
    if(type==="danger") toastEl.classList.add("danger");
    toastEl.style.display = "block";
    setTimeout(()=> toastEl.style.display = "none", 4000);
  }

  function autoSubmit(reason){
    const form = document.getElementById(QUIZ_FORM_ID);
    let i = document.createElement("input");
    i.type = "hidden"; i.name = "auto_submit_reason"; i.value = reason; form.appendChild(i);
    try{ prepareAndAttachAnswers(); }catch(e){}
    isSubmitting = true;
    submittingOverlay.style.display = "flex";
    setTimeout(()=> form.submit(), 400);
  }

  window.addEventListener("blur", ()=> handleTabSwitch("blur"));
  document.addEventListener("visibilitychange", ()=> { if(document.visibilityState === "hidden") handleTabSwitch("visibility"); });

  let lastActive = Date.now(), inactive = false;
  window.addEventListener("focus", ()=> { inactive = false; lastActive = Date.now(); });
  setInterval(()=> {
    const now = Date.now();
    if(!inactive && !document.hidden) lastActive = now;
    else if(now - lastActive > 900){ inactive = true; handleTabSwitch("inactive"); lastActive = now; }
  }, 700);

  function handleTabSwitch(type){
    tabSwitchCount++;
    let remaining = MAX_TAB_SWITCHES - tabSwitchCount;
    if(remaining > 0) showToast(` Do NOT switch tabs! (${remaining} warnings left)`, "warning");
    else { showToast(` Too many tab switches. Auto-submitting...`, "danger"); autoSubmit("tab_switch_limit_exceeded"); }
  }

  function isFS(){ return !!(document.fullscreenElement || document.webkitFullscreenElement); }

  function onFSChange(){
    if(!isFS()){
      fsExitCount++;
      let remaining = MAX_FULLSCREEN_EXITS - fsExitCount;
      if(remaining > 0) showToast(` Return to fullscreen! (${remaining} warnings left)`, "warning");
      else { showToast(` Too many fullscreen exits. Auto-submitting...`, "danger"); autoSubmit("fullscreen_exit_limit_exceeded"); }
    }
  }
  document.addEventListener("fullscreenchange", onFSChange);
  document.addEventListener("webkitfullscreenchange", onFSChange);

  function requestFS(){ let el=document.documentElement; if(el.requestFullscreen) el.requestFullscreen(); else if(el.webkitRequestFullscreen) el.webkitRequestFullscreen(); }

  function startMonitoring(){
    tabSwitchCount = 0; fsExitCount = 0; try{ requestFS(); }catch(e){}
    showToast(" Anti-cheat active. Stay fullscreen & don’t switch tabs.", "warning");
  }
  window.qc_startMonitoring = startMonitoring;
})();
</script>

</body>
</html>
