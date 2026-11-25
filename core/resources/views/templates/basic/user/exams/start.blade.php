@extends('Template::layouts.frontend')

@section('content')
    @php $optionLetters = range('A', 'Z'); $sessionAnswers = []; $sessionAnswerData = []; @endphp
    <section class="exam-section exam-take-page exam-flow my-60">
        <div class="container">
            <form action="{{ route('user.exam.submit', $attendExam->id) }}" method="POST" id="examForm">
                @csrf
                <header class="exam-flow-header">
                    <div class="exam-flow-heading">
                        <span class="exam-kicker">{{ __($exam->category?->name ?? 'Exam') }}</span>
                        <h2>{{ __($exam->title) }}</h2>
                    </div>
                    <div class="exam-flow-tools">
                        <div class="exam-clock {{ $sessionRemaining === null ? 'd-none' : '' }}"><i class="las la-stopwatch"></i> <span class="exam-countdown-time__hrs">00</span>:<span class="exam-countdown-time__min">00</span>:<span class="exam-countdown-time__sec">00</span></div>
                        @if ($showLiveScore)
                            <span class="exam-live-score" id="liveScore"><strong>{{ $liveScore['correct'] }}/{{ $liveScore['total_questions'] }}</strong><b>{{ number_format($liveScore['percentage'], 2) }}%</b></span>
                        @endif
                        <button type="button" class="btn btn--dark btn--sm exam-pause-toggle" id="examPauseToggle" aria-label="@lang('Pause exam')" title="@lang('Pause exam')" aria-pressed="false"><i class="las la-pause" aria-hidden="true"></i><span class="exam-pause-label">@lang('Pause exam')</span></button><button type="button" class="btn btn--dark btn--sm exam-fullscreen-toggle" id="examFullscreenToggle" data-enter-label="@lang('Full screen')" data-exit-label="@lang('Exit full screen')" aria-label="@lang('Enter full screen')" title="@lang('Full screen')" aria-pressed="false">
                            <i class="las la-expand" data-fullscreen-icon aria-hidden="true"></i>
                            <span class="exam-fullscreen-label" data-fullscreen-label>@lang('Full screen')</span>
                        </button>
                    </div>
                </header>

                <div class="exam-flow-layout">
                    <main class="exam-flow-main">
                        <div id="examQuestionContainer">
                            @include('Template::user.exams.partials.question', ['question' => $question, 'questionIndex' => $sessionIndex, 'savedAnswer' => $savedAnswer, 'savedDragData' => $savedDragData, 'revealed' => in_array($sessionIndex, $sessionRevealed), 'dragFeedback' => $dragFeedback])
                        </div>

                        <div class="exam-flow-footer"><button type="button" class="btn btn--dark" id="previousQuestion"><i class="las la-arrow-left" aria-hidden="true"></i><span>@lang('Previous')</span></button><div class="exam-flow-center-actions">@if ($showAnswers)<button type="button" class="btn btn--sm btn-outline--info" id="showAnswer"><i class="las la-lightbulb" aria-hidden="true"></i><span>@lang('Show Answer')</span></button>@endif<button type="button" class="btn btn--sm btn-outline--warning" id="markReview"><i class="las la-flag"></i><span>@lang('Mark for Review')</span></button></div><div class="exam-flow-navigation"><button type="button" class="btn btn--base" id="nextQuestion">@lang('Next')</button><button type="submit" class="btn btn--base d-none" id="submitExam">@lang('Submit Exam')</button></div></div>
                    </main>
                    <aside class="exam-flow-sidebar" id="examQuestionSidebar"><div class="exam-question-nav"><div class="exam-question-nav-title">@lang('Questions')</div><div class="exam-question-nav-grid">@for ($key = 0; $key < $questionCount; $key++)<button type="button" class="question-nav-item {{ $key === $sessionIndex ? 'is-current' : '' }} {{ in_array($key, $sessionIncorrect, true) ? 'is-incorrect' : '' }}" data-go-to="{{ $key }}">{{ $key + 1 }}</button>@endfor</div><div class="exam-nav-legend"><span class="legend-dot answered"></span> @lang('Answered') <span class="legend-dot review"></span> @lang('Review') <span class="legend-dot incorrect"></span> @lang('Incorrect')</div></div></aside>
                </div>
                <div class="exam-mobile-toolbar" id="examMobileToolbar">
                    <div class="exam-mobile-toolbar-top">
                        <div class="exam-mobile-status-slot" id="examMobileStatusSlot"></div>
                        <button type="button" class="btn btn--dark exam-mobile-nav-toggle" id="mobileQuestionNavToggle" aria-expanded="false" aria-controls="mobileQuestionNavDrawer">
                            <i class="las la-list-ul" aria-hidden="true"></i><span>@lang('Questions')</span>
                        </button>
                    </div>
                    <div class="exam-mobile-question-drawer" id="mobileQuestionNavDrawer" hidden></div>
                    <div class="exam-mobile-actions-slot" id="examMobileActionsSlot"></div>
                </div>
                <div class="exam-pause-overlay" id="examPauseDialog" hidden aria-hidden="true">
                    <section class="exam-pause-card" role="dialog" aria-modal="true" aria-labelledby="examPauseTitle">
                        <span class="exam-pause-icon" aria-hidden="true"><i class="las la-pause"></i></span>
                        <h2 id="examPauseTitle">@lang('Exam paused')</h2>
                        <p>@lang('The timer is paused. Resume when you are ready.')</p>
                        <button type="button" class="btn btn--base exam-resume-button" id="resumeExam">@lang('Resume exam')</button>
                    </section>
                </div>
                <div class="exam-action-loader" id="examActionLoader" hidden aria-hidden="true">
                    <div class="exam-action-loader__backdrop"></div>
                    <div class="exam-action-loader__card" role="status" aria-live="polite">
                        <span class="exam-action-loader__spinner" aria-hidden="true"><i></i></span>
                        <span class="exam-action-loader__copy"><strong id="examActionLoaderTitle">@lang('Working on it')</strong><span id="examActionLoaderDetail">@lang('Please wait while this finishes.')</span></span>
                        <span class="exam-action-loader__dots" aria-hidden="true"><i></i><i></i><i></i></span>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection



@push('script')
        <script>
            (function(){
                'use strict';
                const form=document.getElementById('examForm');
                const container=document.getElementById('examQuestionContainer');
                const flowHeader=document.querySelector('.exam-flow-header');
                const flowLayout=document.querySelector('.exam-flow-layout');
                const flowMain=document.querySelector('.exam-flow-main');
                const flowTools=document.querySelector('.exam-flow-tools');
                const examSurface=document.querySelector('.exam-take-page');
                const fullscreenToggle=document.getElementById('examFullscreenToggle');
                const pauseButton=document.getElementById('examPauseToggle');
                const pauseDialog=document.getElementById('examPauseDialog');
                const resumeButton=document.getElementById('resumeExam');
                const actionLoader=document.getElementById('examActionLoader');
                const actionLoaderTitle=document.getElementById('examActionLoaderTitle');
                const actionLoaderDetail=document.getElementById('examActionLoaderDetail');
                const flowFooter=document.querySelector('.exam-flow-footer');
                const flowSidebar=document.getElementById('examQuestionSidebar');
                const mobileToolbar=document.getElementById('examMobileToolbar');
                const mobileStatusSlot=document.getElementById('examMobileStatusSlot');
                const mobileActionsSlot=document.getElementById('examMobileActionsSlot');
                const mobileQuestionNavDrawer=document.getElementById('mobileQuestionNavDrawer');
                const mobileQuestionNavToggle=document.getElementById('mobileQuestionNavToggle');
                const mobileLayoutQuery=window.matchMedia('(max-width: 767px)');
                let actionLoaderHideTimer=null;
                function showActionLoader(title,detail='@lang('Please wait while this finishes.')'){
                    if(!actionLoader)return;
                    window.clearTimeout(actionLoaderHideTimer);
                    if(actionLoaderTitle)actionLoaderTitle.textContent=title;
                    if(actionLoaderDetail)actionLoaderDetail.textContent=detail;
                    actionLoader.hidden=false;
                    actionLoader.setAttribute('aria-hidden','false');
                    requestAnimationFrame(()=>{if(actionLoader.getAttribute('aria-hidden')==='false')actionLoader.classList.add('is-visible')});
                }
                function hideActionLoader(){
                    if(!actionLoader)return;
                    actionLoader.classList.remove('is-visible');
                    actionLoader.setAttribute('aria-hidden','true');
                    actionLoaderHideTimer=window.setTimeout(()=>{
                        if(actionLoader.getAttribute('aria-hidden')==='true')actionLoader.hidden=true;
                    },220);
                }
                function syncFullscreenToggle(){
                    if(!fullscreenToggle)return;
                    const isFullscreen=document.fullscreenElement===examSurface;
                    const label=isFullscreen?fullscreenToggle.dataset.exitLabel:fullscreenToggle.dataset.enterLabel;
                    const labelNode=fullscreenToggle.querySelector('[data-fullscreen-label]');
                    const icon=fullscreenToggle.querySelector('[data-fullscreen-icon]');
                    if(labelNode)labelNode.textContent=label;
                    if(icon){icon.classList.toggle('la-expand',!isFullscreen);icon.classList.toggle('la-compress',isFullscreen)}
                    fullscreenToggle.setAttribute('aria-label',label);
                    fullscreenToggle.setAttribute('title',label);
                    fullscreenToggle.setAttribute('aria-pressed',String(isFullscreen));
                }
                fullscreenToggle?.addEventListener('click',async()=>{
                    const isFullscreen=document.fullscreenElement===examSurface;
                    showActionLoader(isFullscreen?'@lang('Leaving full screen')':'@lang('Opening full screen')','@lang('Updating your exam view.')');
                    try{
                        if(isFullscreen)await document.exitFullscreen();
                        else if(examSurface?.requestFullscreen)await examSurface.requestFullscreen();
                        else throw new Error('Fullscreen is not available in this browser.');
                    }catch(error){notify('error','@lang('Unable to open fullscreen mode in this browser.')')}
                    finally{hideActionLoader()}
                });
                document.addEventListener('fullscreenchange',syncFullscreenToggle);
                syncFullscreenToggle();
                function arrangeMobileExam(){
                    if(mobileLayoutQuery.matches){
                        mobileStatusSlot?.append(flowTools);
                        mobileActionsSlot?.append(flowFooter);
                        mobileQuestionNavDrawer?.append(flowSidebar);
                    }else{
                        flowHeader?.append(flowTools);
                        flowMain?.append(flowFooter);
                        flowLayout?.append(flowSidebar);
                        if(mobileQuestionNavDrawer)mobileQuestionNavDrawer.hidden=true;
                        mobileQuestionNavToggle?.setAttribute('aria-expanded','false');
                    }
                }
                arrangeMobileExam();
                mobileLayoutQuery.addEventListener?.('change',arrangeMobileExam);
                const nav=[...document.querySelectorAll('.question-nav-item')];
                const prev=document.getElementById('previousQuestion');
                const next=document.getElementById('nextQuestion');
                const submit=document.getElementById('submitExam');
                const review=document.getElementById('markReview');
                const showAnswer=document.getElementById('showAnswer');
                const scoreBox=document.getElementById('liveScore');
                const totalQuestions=Number(@json($questionCount));
                const questionUrl='{{ route('user.exam.question', ['id' => $attendExam->id, 'index' => '__INDEX__'], false) }}';
                const answerUrl='{{ route('user.exam.answer.reveal', ['id' => $attendExam->id, 'index' => '__INDEX__'], false) }}';
                const dragConfirmUrl='{{ route('user.exam.drag-drop.confirm', ['id' => $attendExam->id, 'index' => '__INDEX__'], false) }}';
                const saveUrl='{{ route('user.exam.session.save', $attendExam->id, false) }}';
                const csrfToken=document.querySelector('meta[name="csrf-token"]')?.content||'{{ csrf_token() }}';
                const inlineFeedback=@json((bool) ($settings['inline_feedback'] ?? false));
                let current=Number(@json($sessionIndex))||0;
                let reviewed=new Set((@json($sessionFlagged)||[]).map(Number));
                let revealed=new Set((@json($sessionRevealed)||[]).map(Number));
                let committed=new Set((@json($sessionCommitted)||[]).map(Number));
                let incorrectIndexes=new Set((@json($sessionIncorrect)||[]).map(Number));
                let answeredIndexes=new Set((@json($answeredIndexes)||[]).map(Number));
                let secondsRemaining=@json($sessionRemaining);
                let timerPaused=Boolean(@json($sessionPaused))||document.hidden,loading=false,submitting=false,submittedByTimer=false;
                let sessionSaveQueue=Promise.resolve(),dragConfirmationPending=false;
                const step=()=>container?.querySelector('.exam-step');
                function hasAnswer(node=step()){
                    if(!node)return false;
                    const choice=[...node.querySelectorAll('.answer-input')].some(input=>input.checked);
                    const drag=node.querySelector('.drag-answer-data');
                    try{return choice||Boolean(drag&&Object.keys(JSON.parse(drag.value||'{}')).length)}catch(e){return choice}
                }
                function requiredActionComplete(node=step()){
                    if(!node)return false;
                    const inputs=[...node.querySelectorAll('.answer-input')];
                    if(inputs.length){
                        const selected=inputs.filter(input=>input.checked).length;
                        return inputs.some(input=>input.type==='checkbox')
                            ? selected===Math.max(1,Number(node.dataset.requiredSelections)||2)
                            : selected===1;
                    }
                    const drag=node.querySelector('.drag-question');
                    if(drag){
                        const targets=drag.querySelectorAll('.drag-target').length;
                        try{return targets>0&&Object.keys(JSON.parse(drag.querySelector('.drag-answer-data')?.value||'{}')).length===targets&&committed.has(current)}catch(e){return false}
                    }
                    return false;
                }
                function currentPayload(){
                    const node=step(),inputs=[...(node?.querySelectorAll('.answer-input')||[])],checked=inputs.filter(input=>input.checked).map(input=>Number(input.value)),drag=node?.querySelector('.drag-answer-data');
                    let answer=checked.length?(inputs.some(input=>input.type==='checkbox')?checked:checked[0]):null,answerData=null;
                    if(drag){try{const parsed=JSON.parse(drag.value||'{}');if(Object.keys(parsed).length){answerData=parsed;answer=1}}catch(e){}}
                    if(node?.dataset.answerBeforeReveal){
                        const submitted=JSON.parse(node.dataset.answerBeforeReveal);
                        answer=submitted.answer;
                        answerData=submitted.answerData;
                    }
                    return {questionId:node?.dataset.questionId||null,answer,answerData};
                }
                function sessionPayload(){
                    const payload=currentPayload();
                    return {question_id:payload.questionId,current_question:current,remaining_seconds:secondsRemaining,is_paused:timerPaused,answer:payload.answer,answer_data:payload.answerData,flagged_questions:[...reviewed],revealed_questions:[...revealed],committed_questions:[...committed]};
                }
                function updateScore(score){
                    if(!scoreBox||!score)return;
                    scoreBox.innerHTML='<strong>'+Number(score.correct||0)+'/'+Number(score.total_questions||0)+'</strong><b>'+Number(score.percentage||0).toFixed(2)+'%</b>';
                }
                function updatePauseDialog(){
                    if(!pauseDialog)return;
                    pauseDialog.hidden=!timerPaused;
                    pauseDialog.setAttribute('aria-hidden',String(!timerPaused));
                    pauseButton?.setAttribute('aria-pressed',String(timerPaused));
                }
                function setPaused(paused,shouldPersist=true){
                    if(submitting||submittedByTimer)return;
                    const nextState=Boolean(paused),changed=timerPaused!==nextState;
                    timerPaused=nextState;
                    updatePauseDialog();
                    renderClock();
                    if(shouldPersist&&changed)persistSession();
                }
                async function handlePauseAction(paused){
                    if(loading||submitting||dragConfirmationPending)return;
                    const changed=timerPaused!==Boolean(paused);
                    setPaused(paused,false);
                    if(!changed)return;
                    showActionLoader(paused?'@lang('Pausing exam')':'@lang('Resuming exam')','@lang('Saving your exam progress.')');
                    try{await persistSession()}
                    finally{hideActionLoader()}
                }
                pauseButton?.addEventListener('click',()=>handlePauseAction(true));
                resumeButton?.addEventListener('click',()=>handlePauseAction(false));
                function fetchJsonWithTimeout(url,options,timeoutMs){
                    const controller=new AbortController();
                    let timeoutId;
                    const request=(async()=>{
                        const response=await fetch(url,{...options,signal:controller.signal});
                        const payload=await response.json();
                        return {response,payload};
                    })();
                    const timeout=new Promise((resolve,reject)=>{
                        timeoutId=window.setTimeout(()=>{controller.abort();reject(new Error('Request timed out'))},timeoutMs);
                    });
                    return Promise.race([request,timeout]).finally(()=>window.clearTimeout(timeoutId));
                }
                async function persistSession(){
                    if(!saveUrl||!form||!step()||dragConfirmationPending)return null;
                    try{
                        sessionSaveQueue=sessionSaveQueue.catch(()=>null).then(async()=>{
                            const {response,payload}=await fetchJsonWithTimeout(saveUrl,{method:'POST',keepalive:true,headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken,'Cache-Control':'no-cache'},body:JSON.stringify(sessionPayload())},7000);
                            return response.ok?payload:null;
                        }).catch(()=>null);
                        const payload=await sessionSaveQueue;
                        if(!payload?.success)return null;
                        updateScore(payload.live_score);return payload;
                    }catch(error){return null}
                }
                function keepQuestionInView(){
                    const rect=container.getBoundingClientRect();
                    const headerHeight=parseFloat(getComputedStyle(document.documentElement).getPropertyValue('--header-h'))||70;
                    const topOffset=mobileLayoutQuery.matches?Math.max(100,headerHeight+14):115;
                    const bottomOffset=mobileLayoutQuery.matches?(mobileToolbar?.offsetHeight||180)+12:90;
                    if(rect.top<topOffset||rect.top>window.innerHeight-bottomOffset){window.scrollTo({top:window.scrollY+rect.top-topOffset,behavior:'smooth'})}
                }
                function refresh(keepView=false){
                    nav.forEach((button,index)=>{const incorrect=incorrectIndexes.has(index);button.classList.toggle('is-current',index===current);button.classList.toggle('is-answered',answeredIndexes.has(index)&&!incorrect);button.classList.toggle('is-review',reviewed.has(index));button.classList.toggle('is-incorrect',incorrect);button.disabled=loading});
                    prev.disabled=loading||current===0;next.disabled=loading;const readyForNext=requiredActionComplete();next.textContent=readyForNext?'@lang('Next')':'@lang('Skip')';next.setAttribute('aria-label',readyForNext?'@lang('Next')':'@lang('Skip')');next.classList.toggle('btn--dark',!readyForNext);next.classList.toggle('btn--base',readyForNext);next.classList.toggle('d-none',current===totalQuestions-1);submit.classList.toggle('d-none',current!==totalQuestions-1);
                    review.disabled=loading;review.classList.toggle('btn--warning',reviewed.has(current));
                    review.innerHTML=reviewed.has(current)?'<i class="las la-flag"></i><span>@lang('Remove Review')</span>':'<i class="las la-flag"></i><span>@lang('Mark for Review')</span>';
                    if(showAnswer){showAnswer.disabled=loading||revealed.has(current);showAnswer.innerHTML=revealed.has(current)?'<i class="las la-check-circle" aria-hidden="true"></i><span>@lang('Answer revealed')</span>':'<i class="las la-lightbulb" aria-hidden="true"></i><span>@lang('Show Answer')</span>'}
                    if(keepView)keepQuestionInView();
                }
                function renderClock(){
                    const clock=document.querySelector('.exam-clock');
                    if(secondsRemaining===null||secondsRemaining===undefined){clock?.classList.add('d-none');return}
                    clock?.classList.remove('d-none');
                    const safe=Math.max(0,Number(secondsRemaining)||0);
                    document.querySelector('.exam-countdown-time__hrs').textContent=String(Math.floor(safe/3600)).padStart(2,'0');
                    document.querySelector('.exam-countdown-time__min').textContent=String(Math.floor((safe%3600)/60)).padStart(2,'0');
                    document.querySelector('.exam-countdown-time__sec').textContent=String(safe%60).padStart(2,'0');
                }
                function decorateExplanation(area){
                    if(!area)return;
                    area.querySelectorAll('.section-title-correct,.section-title-incorrect').forEach(heading=>{
                        if(heading.dataset.explanationDecorated)return;
                        heading.dataset.explanationDecorated='true';
                        const isCorrect=heading.classList.contains('section-title-correct');
                        heading.textContent=heading.textContent.replace(isCorrect?/^[✓✔]\s*/:/^[✗✘✕×]\s*/,'');
                        const icon=document.createElement('i');icon.className='las '+(isCorrect?'la-check-circle text--success':'la-times-circle text--danger');icon.setAttribute('aria-hidden','true');heading.prepend(icon);
                    });
                }
                function renderFeedback(payload,submittedPayload){
                    const node=step();if(!node)return;
                    node.dataset.answerBeforeReveal=JSON.stringify({answer:submittedPayload?.answer??null,answerData:submittedPayload?.answerData??null});
                    const correctIds=new Set((payload.correct_option_ids||[]).map(Number));
                    const originallySelectedIds=new Set([...node.querySelectorAll('.answer-input:checked')].map(input=>Number(input.value)));
                    node.querySelectorAll('.exam-qsn-item').forEach(label=>{
                        const input=label.querySelector('.answer-input');if(!input)return;
                        const optionId=Number(input.value),wasSelected=originallySelectedIds.has(optionId),isCorrect=correctIds.has(optionId);
                        input.checked=isCorrect;
                        label.classList.remove('correct-answer');label.classList.toggle('wrong-answer',wasSelected&&!isCorrect);input.disabled=true;
                    });
                    const drag=node.querySelector('.drag-question');
                    if(drag){
                        const submitted=JSON.parse(drag.querySelector('.drag-answer-data')?.value||'{}');
                        drag.querySelectorAll('.drag-target').forEach(target=>{
                            const key=target.dataset.target,expected=payload.correct_matches?.[key],actual=submitted[key];
                            const correct=expected!==undefined&&String(expected)===String(actual),wrong=actual!==undefined&&!correct;
                            target.classList.toggle('text--success',correct);target.classList.toggle('text--danger',wrong);
                        });
                        drag.querySelectorAll('.drag-item').forEach(item=>{item.draggable=false;item.setAttribute('aria-disabled','true')});
                        drag.querySelectorAll('.drag-reset,.drag-confirm,.remove-item-btn').forEach(button=>button.disabled=true);
                    }
                    let feedback=node.querySelector('.answer-feedback');
                    if(!feedback){feedback=document.createElement('div');node.append(feedback)}
                    feedback.className='mt-3 mb-3';feedback.setAttribute('role','status');
                    feedback.innerHTML='<span class="badge '+(payload.is_correct?'badge--success':'badge--danger')+'"><i class="las '+(payload.is_correct?'la-check-circle':'la-times-circle')+'" aria-hidden="true"></i> '+(payload.is_correct?'@lang('Correct')':'@lang('Incorrect — correct answer highlighted')')+'</span>';
                    let explanation=node.querySelector('.exam-explanation');
                    if(!explanation){explanation=document.createElement('div');explanation.className='exam-explanation';node.append(explanation)}
                    explanation.innerHTML=payload.explanation||'';explanation.classList.add('is-visible');decorateExplanation(explanation);
                }
                async function revealCurrent(force=false){
                    if(loading&&!force)return false;
                    const ownsLoading=!force;
                    if(ownsLoading){loading=true;refresh();showActionLoader('@lang('Checking answer')','@lang('Your answer is being checked.')')}
                    const payload=currentPayload();
                    try{
                        const {response,payload:result}=await fetchJsonWithTimeout(answerUrl.replace('__INDEX__',String(current)),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({answer:payload.answer,answer_data:payload.answerData})},8000);
                        if(!response.ok)throw new Error('Unable to check this answer');
                        const wasAnswered=hasAnswer();revealed.add(current);committed.add(current);if(wasAnswered)answeredIndexes.add(current);
                        if(result.is_correct)incorrectIndexes.delete(current);else if(wasAnswered)incorrectIndexes.add(current);else incorrectIndexes.delete(current);
                        renderFeedback(result,payload);updateScore(result.live_score);refresh();await persistSession();return true;
                    }catch(error){notify('error','@lang('Unable to check this answer. Please try again.')');return false}
                    finally{if(ownsLoading){loading=false;refresh();hideActionLoader()}}
                }
                function bindDrag(){
                    const question=document.querySelector('.drag-question');
                    if(!question)return;
                    const hidden=question.querySelector('.drag-answer-data');
                    const answerInput=question.querySelector('input[name^="answer["]');
                    const initial=JSON.parse(hidden?.value||'{}');
                    const placements={...initial};
                    const items=[...question.querySelectorAll('.drag-item')];
                    const targets=[...question.querySelectorAll('.drag-target')];
                    const confirmButton=question.querySelector('.drag-confirm');
                    const feedbackBox=question.querySelector('.drag-answer-feedback');
                    let selected=null,dragged=null,checking=false,feedback=null;
                    try{feedback=JSON.parse(question.dataset.dragFeedback||'null')}catch(error){feedback=null}
                    const targetForItem=item=>Object.keys(placements).find(target=>placements[target]===item)||null;
                    const render=(persist=false)=>{
                        const assigned=Object.values(placements),count=Object.keys(placements).length,confirmed=committed.has(current);
                        items.forEach(item=>{
                            const placed=assigned.includes(item.dataset.item);
                            item.classList.toggle('is-placed',placed);
                            item.classList.toggle('is-assigned',placed);
                            item.classList.toggle('is-selected',selected===item.dataset.item&&!placed);
                            item.draggable=!placed&&!confirmed&&!checking;
                        });
                        targets.forEach(target=>{
                            const name=target.dataset.target,item=placements[name],slot=target.querySelector('.drag-target-slot');
                            const slotResult=feedback?.slot_results?.[name];
                            const status=slotResult?.status||'';
                            target.classList.toggle('ready-for-drop',Boolean(selected&&!item&&!confirmed&&!checking));
                            target.classList.toggle('filled',Boolean(item));
                            target.classList.toggle('is-match-correct',status==='correct');
                            target.classList.toggle('is-match-wrong',status==='wrong');
                            target.classList.toggle('is-match-missing',status==='missing');
                            if(item){
                                const wrapper=document.createElement('div');
                                wrapper.className='assigned-item'+(status==='correct'?' is-match-correct':status==='wrong'?' is-match-wrong':'');
                                const text=document.createElement('span');
                                text.className='assigned-text';
                                text.textContent=item;
                                wrapper.append(text);
                                if(status==='correct'||status==='wrong'){
                                    const indicator=document.createElement('span');
                                    indicator.className='drag-match-indicator '+(status==='correct'?'is-correct':'is-wrong');
                                    indicator.setAttribute('aria-label',status==='correct'?'@lang('Correct')':'@lang('Incorrect')');
                                    indicator.textContent=status==='correct'?'✓':'✕';
                                    wrapper.append(indicator);
                                }
                                if(!confirmed){
                                    const remove=document.createElement('button');
                                    remove.type='button';
                                    remove.className='remove-item-btn';
                                    remove.title='@lang('Remove item')';
                                    remove.textContent='✕';
                                    remove.addEventListener('click',event=>{
                                        event.stopPropagation();
                                        if(checking||committed.has(current))return;
                                        delete placements[name];
                                        selected=null;
                                        render();
                                    });
                                    wrapper.append(remove);
                                }
                                slot.replaceChildren(wrapper);
                            }else{
                                const placeholder=document.createElement('span');
                                placeholder.className='drop-placeholder drag-placeholder';
                                placeholder.innerHTML='<span class="empty-hint">@lang('Drop item here')</span>';
                                slot.replaceChildren(placeholder);
                            }
                            target.setAttribute('aria-disabled',confirmed||checking?'true':'false');
                            target.querySelector('.drag-slot-correction')?.remove();
                            if(status!=='correct'&&slotResult?.correct_item){
                                const correction=document.createElement('div');
                                correction.className='drag-slot-correction';
                                const label=document.createElement('strong');
                                label.textContent='@lang('Correct:')';
                                const correctText=document.createElement('span');
                                correctText.textContent=slotResult.correct_item;
                                correction.append(label,correctText);
                                target.append(correction);
                            }
                        });
                        question.querySelector('.drag-item-count').textContent=(items.length-count)+' @lang('left')';
                        question.querySelector('.drag-match-count').textContent=count+' / '+targets.length+' @lang('assigned')';
                        question.querySelector('.drag-confirm-count').textContent=count+'/'+targets.length;
                        question.querySelector('.drag-reset').classList.toggle('d-none',count===0||committed.has(current)||checking);
                        if(confirmButton){
                            const confirmed=committed.has(current);
                            confirmButton.disabled=checking||confirmed||count===0;
                            confirmButton.innerHTML=checking
                                ? '<span class="drag-action-icon" aria-hidden="true">…</span><span>@lang('Checking matches...')</span>'
                                : confirmed
                                    ? '<span class="drag-action-icon" aria-hidden="true">✓</span><span>@lang('Matches confirmed')</span>'
                                    : '<span class="drag-action-icon" aria-hidden="true">✓</span><span>@lang('Confirm Drag & Drop Matches') (<span class="drag-confirm-count">'+count+'/'+targets.length+'</span>)</span>';
                        }
                        if(feedbackBox){
                            feedbackBox.hidden=!feedback;
                            feedbackBox.className='drag-answer-feedback'+(feedback?(feedback.is_correct?' is-correct':' is-wrong'):'');
                            feedbackBox.textContent=feedback
                                ? (feedback.is_correct?'✓ @lang('Correct')':'✕ @lang('Incorrect. Correct matches are shown below.')')
                                : '';
                        }
                        hidden.value=JSON.stringify(placements);
                        if(answerInput)answerInput.value=count?'1':'';
                        answeredIndexes[count?'add':'delete'](current);
                        refresh();
                        if(persist)persistSession();
                    };
                    const place=(name,item)=>{
                        if(checking||committed.has(current)||!item||!items.some(source=>source.dataset.item===item))return;
                        const old=targetForItem(item);
                        if(old&&old!==name)delete placements[old];
                        if(placements[name]){
                            const displaced=placements[name];
                            const displacedTarget=targetForItem(displaced);
                            if(displacedTarget)delete placements[displacedTarget];
                        }
                        placements[name]=item;
                        selected=null;
                        render();
                    };
                    async function confirmMatches(){
                        if(checking||committed.has(current)||!Object.keys(placements).length)return;
                        const confirmedIndex=current;
                        checking=true;
                        loading=true;
                        dragConfirmationPending=true;
                        render(false);
                        refresh();
                        showActionLoader('@lang('Checking matches')','@lang('Your matches are being checked.')');
                        const submitted={...placements};
                        try{
                            const {response,payload:result}=await fetchJsonWithTimeout(dragConfirmUrl.replace('__INDEX__',String(confirmedIndex)),{
                                method:'POST',
                                headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken,'X-Requested-With':'XMLHttpRequest'},
                                body:JSON.stringify({answer:1,answer_data:submitted})
                            },6000);
                            if(!response.ok)throw new Error('Unable to confirm these matches.');
                            if(!result.success)throw new Error('Unable to confirm these matches.');
                            feedback=result;
                            committed.add(confirmedIndex);
                            answeredIndexes.add(confirmedIndex);
                            if(result.is_correct)incorrectIndexes.delete(confirmedIndex);else incorrectIndexes.add(confirmedIndex);
                            question.classList.add('is-confirmed');
                            checking=false;
                            loading=false;
                            dragConfirmationPending=false;
                            render(false);
                            updateScore(result.live_score);
                            hideActionLoader();
                        }catch(error){
                            checking=false;
                            loading=false;
                            dragConfirmationPending=false;
                            render(false);
                            notify('error','@lang('Unable to confirm these matches. Please try again.')');
                            hideActionLoader();
                        }
                    }
                    items.forEach(item=>{
                        item.addEventListener('dragstart',event=>{
                            if(checking||committed.has(current)||item.classList.contains('is-placed')){event.preventDefault();return}
                            dragged=item.dataset.item;
                            event.dataTransfer.effectAllowed='move';
                            event.dataTransfer.setData('text/plain',dragged);
                        });
                        item.addEventListener('dragend',()=>{
                            dragged=null;
                            targets.forEach(target=>target.classList.remove('is-over'));
                        });
                        item.addEventListener('click',()=>{
                            if(checking||committed.has(current))return;
                            if(item.classList.contains('is-placed')){
                                const target=targetForItem(item.dataset.item);
                                if(target){delete placements[target];render()}
                                return;
                            }
                            selected=selected===item.dataset.item?null:item.dataset.item;
                            render();
                        });
                    });
                    targets.forEach(target=>{
                        target.addEventListener('dragover',event=>{
                            if(checking||committed.has(current))return;
                            event.preventDefault();
                            target.classList.add('is-over');
                        });
                        target.addEventListener('dragleave',()=>target.classList.remove('is-over'));
                        target.addEventListener('drop',event=>{
                            if(checking||committed.has(current))return;
                            event.preventDefault();
                            target.classList.remove('is-over');
                            place(target.dataset.target,event.dataTransfer.getData('text/plain')||dragged);
                        });
                        target.addEventListener('click',()=>{
                            if(checking||committed.has(current))return;
                            if(selected)place(target.dataset.target,selected);
                            else if(placements[target.dataset.target]){delete placements[target.dataset.target];render()}
                        });
                    });
                    question.querySelector('.drag-reset').addEventListener('click',()=>{
                        if(checking||committed.has(current))return;
                        Object.keys(placements).forEach(key=>delete placements[key]);
                        selected=null;
                        render();
                    });
                    confirmButton?.addEventListener('click',confirmMatches);
                    render();
                }
                function bindImageViewers(root){
                    root.querySelectorAll('[data-exam-image-viewer]').forEach(viewer=>{
                        if(viewer.dataset.zoomBound)return;
                        viewer.dataset.zoomBound='true';
                        let zoom=1;
                        const output=viewer.querySelector('[data-zoom-level]');
                        const zoomIn=viewer.querySelector('[data-zoom-action="in"]');
                        const zoomOut=viewer.querySelector('[data-zoom-action="out"]');
                        const frame=viewer.querySelector('.exam-image-frame');
                        let panPointer=null;
                        let panStart=null;
                        const stopPanning=event=>{
                            if(panPointer===null||(event&&event.pointerId!==panPointer))return;
                            panPointer=null;
                            panStart=null;
                            viewer.classList.remove('is-panning');
                        };
                        const update=()=>{
                            zoom=Number(zoom.toFixed(2));
                            viewer.dataset.zoom=String(zoom);
                            viewer.classList.toggle('is-zoomed',zoom>1);
                            if(output)output.textContent=Math.round(zoom*100)+'%';
                            if(zoomIn)zoomIn.disabled=zoom>=3.5;
                            if(zoomOut)zoomOut.disabled=zoom<=1;
                            if(zoom<=1&&frame){frame.scrollLeft=0;frame.scrollTop=0;stopPanning()}
                        };
                        if(frame){
                            frame.addEventListener('pointerdown',event=>{
                                if(zoom<=1||event.button!==0||event.isPrimary===false)return;
                                panPointer=event.pointerId;
                                panStart={x:event.clientX,y:event.clientY,left:frame.scrollLeft,top:frame.scrollTop};
                                viewer.classList.add('is-panning');
                                frame.setPointerCapture(event.pointerId);
                                event.preventDefault();
                            });
                            frame.addEventListener('pointermove',event=>{
                                if(panPointer!==event.pointerId||!panStart)return;
                                frame.scrollLeft=panStart.left-(event.clientX-panStart.x);
                                frame.scrollTop=panStart.top-(event.clientY-panStart.y);
                                event.preventDefault();
                            });
                            frame.addEventListener('pointerup',stopPanning);
                            frame.addEventListener('pointercancel',stopPanning);
                            frame.addEventListener('lostpointercapture',stopPanning);
                        }
                        viewer.querySelectorAll('[data-zoom-action]').forEach(button=>button.addEventListener('click',()=>{
                            if(button.dataset.zoomAction==='in')zoom=Math.min(3.5,zoom+0.1);
                            else if(button.dataset.zoomAction==='out')zoom=Math.max(1,zoom-0.1);
                            else zoom=1;
                            update();
                        }));
                        update();
                    });
                    root.querySelectorAll('[data-source-toggle]').forEach(button=>{
                        if(button.dataset.sourceBound)return;
                        const panel=document.getElementById(button.getAttribute('aria-controls'));
                        if(!panel)return;
                        button.dataset.sourceBound='true';
                        button.addEventListener('click',()=>{
                            panel.hidden=!panel.hidden;
                            button.setAttribute('aria-expanded',String(!panel.hidden));
                            const label=button.querySelector('[data-source-toggle-label]');
                            if(label)label.textContent=panel.hidden?button.dataset.closedLabel:button.dataset.openLabel;
                        });
                    });
                }
                function bindCurrent(){
                    const node=step();if(!node)return;
                    const multipleInputs=[...node.querySelectorAll('.answer-input[type="checkbox"]')];
                    const selectionLimit=Math.max(1,Number(node.dataset.requiredSelections)||2);
                    const checkedMultiple=multipleInputs.filter(input=>input.checked);
                    checkedMultiple.slice(selectionLimit).forEach(input=>{input.checked=false});
                    node.querySelectorAll('.answer-input').forEach(input=>{
                        input.disabled=committed.has(current);
                        input.addEventListener('change',()=>{
                            if(input.type==='checkbox'&&input.checked&&multipleInputs.filter(option=>option.checked).length>selectionLimit){input.checked=false;return}
                            if(hasAnswer())answeredIndexes.add(current);else answeredIndexes.delete(current);
                            refresh();persistSession()
                        })
                    });
                    bindImageViewers(node);bindDrag();refresh();
                }
                async function loadQuestion(index,commitBeforeMove=false,actionTitle='@lang('Loading question')'){
                    if(loading||index<0||index>=totalQuestions)return;
                    loading=true;container.classList.add('is-loading');refresh();showActionLoader(actionTitle,'@lang('Your exam is being saved and the question is loading.')');
                    try{
                        if(commitBeforeMove&&inlineFeedback&&hasAnswer()&&!revealed.has(current)){const committedOk=await revealCurrent(true);if(!committedOk)return}
                        else await persistSession();
                        const {response,payload}=await fetchJsonWithTimeout(questionUrl.replace('__INDEX__',String(index)),{headers:{Accept:'application/json','X-Requested-With':'XMLHttpRequest','Cache-Control':'no-cache'}},12000);
                        if(!response.ok)throw new Error('Question request failed');
                        container.innerHTML=payload.html;current=index;
                        if(payload.answered)answeredIndexes.add(index);else answeredIndexes.delete(index);
                        if(payload.revealed){revealed.add(index);committed.add(index)}
                        bindCurrent();refresh(true);
                        if(payload.revealed)window.setTimeout(()=>revealCurrent(),0);
                    }catch(error){notify('error','@lang('Unable to load this question. Please try again.')')}
                    finally{loading=false;container.classList.remove('is-loading');refresh();hideActionLoader()}
                }
                next.addEventListener('click',()=>{const canContinue=requiredActionComplete();loadQuestion(current+1,canContinue,canContinue?'@lang('Loading next question')':'@lang('Skipping question')')});
                prev.addEventListener('click',()=>loadQuestion(current-1,false,'@lang('Loading previous question')'));
                mobileQuestionNavToggle?.addEventListener('click',()=>{
                    const isOpen=mobileQuestionNavDrawer?.hidden??true;
                    if(mobileQuestionNavDrawer)mobileQuestionNavDrawer.hidden=!isOpen;
                    mobileQuestionNavToggle.setAttribute('aria-expanded',String(isOpen));
                });
                nav.forEach(button=>button.addEventListener('click',()=>{
                    if(mobileQuestionNavDrawer)mobileQuestionNavDrawer.hidden=true;
                    mobileQuestionNavToggle?.setAttribute('aria-expanded','false');
                    loadQuestion(Number(button.dataset.goTo),false,'@lang('Opening question')');
                }));
                review.addEventListener('click',async()=>{
                    if(loading||submitting)return;
                    reviewed.has(current)?reviewed.delete(current):reviewed.add(current);
                    loading=true;refresh();showActionLoader('@lang('Saving review status')','@lang('Your exam progress is being saved.')');
                    try{await persistSession()}
                    finally{loading=false;refresh();hideActionLoader()}
                });
                showAnswer?.addEventListener('click',revealCurrent);
                document.addEventListener('visibilitychange',()=>{
                    if(document.hidden)setPaused(true);
                    else{updatePauseDialog();renderClock();if(timerPaused)persistSession()}
                });
                window.addEventListener('blur',()=>setPaused(true));
                window.addEventListener('pagehide',()=>setPaused(true));
                form?.addEventListener('submit',async event=>{if(submittedByTimer){showActionLoader('@lang('Submitting exam')','@lang('Your answers are being submitted.')');return}event.preventDefault();if(submitting)return;submitting=true;showActionLoader('@lang('Submitting exam')','@lang('Your answers are being submitted.')');form.querySelectorAll('button[type="submit"]').forEach(button=>{button.disabled=true;button.innerHTML='<i class="las la-spinner la-spin"></i> @lang('Submitting...')'});await persistSession();HTMLFormElement.prototype.submit.call(form)});
                function tick(){if(secondsRemaining===null||secondsRemaining===undefined||timerPaused||dragConfirmationPending)return;if(Number(secondsRemaining)<=0){if(!submittedByTimer){submittedByTimer=true;showActionLoader('@lang('Time is up')','@lang('Your exam is being submitted.')');persistSession().finally(()=>HTMLFormElement.prototype.submit.call(form))}return}secondsRemaining=Number(secondsRemaining)-1;renderClock();if(secondsRemaining%5===0&&!step()?.querySelector('.drag-question'))persistSession()}
                bindCurrent();renderClock();updatePauseDialog();if(timerPaused)persistSession();if(secondsRemaining!==null&&secondsRemaining!==undefined){tick();setInterval(tick,1000)}
                if(revealed.has(current))revealCurrent();
            })();
        </script>
        <template>
        (function($){'use strict';const form=document.getElementById('examForm'),steps=[...document.querySelectorAll('.exam-step')],nav=[...document.querySelectorAll('.question-nav-item')],prev=document.getElementById('previousQuestion'),next=document.getElementById('nextQuestion'),submit=document.getElementById('submitExam'),review=document.getElementById('markReview'),showAnswer=document.getElementById('showAnswer'),saveUrl='{{ route('user.exam.session.save', $attendExam->id) }}',csrfToken=document.querySelector('meta[name="csrf-token"]')?.content||'{{ csrf_token() }}';let current=Math.max(0,Math.min(steps.length-1,Number(@json($sessionIndex))||0)),reviewed=new Set((@json($sessionFlagged) || []).map(Number)),revealed=new Set((@json($sessionRevealed) || []).map(Number)),committed=new Set((@json($sessionCommitted) || []).map(Number)),secondsRemaining=@json($sessionRemaining === null ? ((int) $exam->duration * 60) : (int) $sessionRemaining),timerPaused=document.hidden,saveTimer=null,submittedByTimer=false;
            const savedAnswers=@json($sessionAnswers),savedAnswerData=@json($sessionAnswerData);
            steps.forEach(step=>{const questionId=step.dataset.questionId,saved=savedAnswers?.[questionId],savedValues=Array.isArray(saved)?saved.map(String):(saved===undefined||saved===null?[]:[String(saved)]);step.querySelectorAll('.answer-input').forEach(input=>{input.checked=savedValues.includes(String(input.value))});const drag=step.querySelector('.drag-answer-data');if(drag)drag.value=JSON.stringify(savedAnswerData?.[questionId]||{});});
            function answered(step){if(!step)return false;const choice=[...step.querySelectorAll('.answer-input')].some(input=>input.checked),drag=step.querySelector('.drag-answer-data');let dragAnswered=false;try{dragAnswered=Boolean(drag&&Object.keys(JSON.parse(drag.value||'{}')).length)}catch(e){}return choice||dragAnswered}
            function collectAnswers(){const answers={},answerData={};steps.forEach(step=>{const id=step.dataset.questionId,inputs=[...step.querySelectorAll('.answer-input')],checked=inputs.filter(input=>input.checked).map(input=>Number(input.value));if(checked.length)answers[id]=inputs.some(input=>input.type==='checkbox')?checked:checked[0];const drag=step.querySelector('.drag-answer-data');if(drag){try{const value=JSON.parse(drag.value||'{}');if(Object.keys(value).length)answerData[id]=value}catch(e){}}});return {answers,answerData}}
            function sessionPayload(){const collected=collectAnswers();return { _token:csrfToken, current_question:current, remaining_seconds:secondsRemaining, answers:collected.answers, answer_data:collected.answerData, flagged_questions:[...reviewed], revealed_questions:[...revealed], committed_questions:[...committed] }}
            function persistSession(){if(!saveUrl||!form)return Promise.resolve();const request=fetch(saveUrl,{method:'POST',keepalive:true,headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken,'Cache-Control':'no-cache'},body:JSON.stringify(sessionPayload())}).catch(()=>null);return request}
            function refresh(shouldScroll=false){steps.forEach((step,i)=>step.classList.toggle('is-active',i===current));nav.forEach((button,i)=>{button.classList.toggle('is-current',i===current);button.classList.toggle('is-answered',answered(steps[i]));button.classList.toggle('is-review',reviewed.has(i))});$('#questionCounter').text('@lang('Question') '+(current+1)+' @lang('of') '+steps.length);$('#answeredCount').text(steps.filter(answered).length);prev.disabled=current===0;next.classList.toggle('d-none',current===steps.length-1);submit.classList.toggle('d-none',current!==steps.length-1);review.classList.toggle('btn--warning',reviewed.has(current));review.innerHTML=reviewed.has(current)?'<i class="las la-flag"></i><span>@lang('Remove Review')</span>':'<i class="las la-flag"></i><span>@lang('Mark for Review')</span>';if(showAnswer){const area=steps[current].querySelector('.exam-explanation');showAnswer.innerHTML=area?.classList.contains('is-visible')?'<i class="las la-eye-slash"></i> @lang('Hide Answer')':'<i class="las la-lightbulb"></i> @lang('Show Answer')'}if(shouldScroll)steps[current].scrollIntoView({behavior:'smooth',block:'start'})}
            function renderClock(){const safe=Math.max(0,Number(secondsRemaining)||0);$('.exam-countdown-time__hrs').text(String(Math.floor(safe/3600)).padStart(2,'0'));$('.exam-countdown-time__min').text(String(Math.floor((safe%3600)/60)).padStart(2,'0'));$('.exam-countdown-time__sec').text(String(safe%60).padStart(2,'0'))}
            function tick(){if(timerPaused)return;if(secondsRemaining<=0){if(!submittedByTimer&&form){submittedByTimer=true;persistSession().finally(()=>form.submit())}return}secondsRemaining-=1;renderClock();if(secondsRemaining%5===0)persistSession()}renderClock();tick();setInterval(tick,1000);
            next.addEventListener('click',()=>{if(current<steps.length-1){persistSession();current++;refresh(true)}});prev.addEventListener('click',()=>{if(current>0){persistSession();current--;refresh(true)}});nav.forEach((button,i)=>button.addEventListener('click',()=>{persistSession();current=i;refresh(true)}));review.addEventListener('click',()=>{reviewed.has(current)?reviewed.delete(current):reviewed.add(current);refresh();persistSession()});showAnswer?.addEventListener('click',()=>{const area=steps[current].querySelector('.exam-explanation');if(!area)return;area.classList.toggle('is-visible');if(area.classList.contains('is-visible'))steps[current].querySelectorAll('[data-correct="1"]').forEach(option=>option.classList.add('is-correct'));revealed.add(current);refresh();persistSession()});document.querySelectorAll('.answer-input').forEach(input=>input.addEventListener('change',()=>{refresh();persistSession()}));
            document.querySelectorAll('.drag-question').forEach(question=>{
                const hidden=question.querySelector('.drag-answer-data'),initialPlacements=JSON.parse(hidden?.value||'{}'),placements={...initialPlacements},items=[...question.querySelectorAll('.drag-item')],targets=[...question.querySelectorAll('.drag-target')];
                let selected=null,dragged=null;
                const targetForItem=item=>Object.keys(placements).find(target=>placements[target]===item)||null;
                const remove=(targetName)=>{if(!Object.prototype.hasOwnProperty.call(placements,targetName))return;delete placements[targetName];selected=null;render()};
                const place=(targetName,item)=>{
                    if(!item||!items.some(source=>source.dataset.item===item))return;
                    const previousTarget=targetForItem(item);
                    if(previousTarget&&previousTarget!==targetName)delete placements[previousTarget];
                    placements[targetName]=item;selected=null;render();
                };
                const render=()=>{
                    const assigned=Object.values(placements),assignedCount=Object.keys(placements).length;
                    items.forEach(item=>{
                        const isPlaced=assigned.includes(item.dataset.item);
                        item.classList.toggle('is-placed',isPlaced);
                        item.classList.toggle('is-selected',selected===item.dataset.item&&!isPlaced);
                        item.draggable=!isPlaced;
                        item.title=isPlaced?'Click to recall this item':'Click to select or drag to a target';
                    });
                    targets.forEach(target=>{
                        const targetName=target.dataset.target,item=placements[targetName],slot=target.querySelector('.drag-target-slot');
                        target.classList.toggle('ready-for-drop',Boolean(selected&&!item));
                        if(item){
                            slot.innerHTML='<div class="drag-assigned-item"><span class="drag-handle"><i class="las la-grip-vertical"></i></span><span class="drag-item-text"></span><button type="button" class="drag-remove" title="@lang('Remove item')"><i class="las la-times"></i></button></div>';
                            slot.querySelector('.drag-item-text').textContent=item;
                            slot.querySelector('.drag-remove').addEventListener('click',event=>{event.stopPropagation();remove(targetName)});
                        }else{
                            slot.innerHTML='<span class="drag-placeholder">@lang('Drop an item here')</span>';
                        }
                    });
                    question.querySelector('.drag-item-count').textContent=(items.length-assignedCount)+' @lang('left')';
                    question.querySelector('.drag-match-count').textContent=assignedCount+' / '+targets.length+' @lang('assigned')';
                    question.querySelector('.drag-reset').classList.toggle('d-none',assignedCount===0);
                    hidden.value=JSON.stringify(placements);refresh();persistSession();
                };
                items.forEach(item=>{
                    item.addEventListener('dragstart',event=>{if(item.classList.contains('is-placed')){event.preventDefault();return}dragged=item.dataset.item;event.dataTransfer.effectAllowed='move';event.dataTransfer.setData('text/plain',dragged)});
                    item.addEventListener('dragend',()=>{dragged=null;targets.forEach(target=>target.classList.remove('is-over'))});
                    item.addEventListener('click',()=>{if(item.classList.contains('is-placed')){const assignedTarget=targetForItem(item.dataset.item);if(assignedTarget)remove(assignedTarget);return}selected=selected===item.dataset.item?null:item.dataset.item;render()});
                });
                targets.forEach(target=>{
                    target.addEventListener('dragover',event=>{event.preventDefault();target.classList.add('is-over');event.dataTransfer.dropEffect='move'});
                    target.addEventListener('dragleave',()=>target.classList.remove('is-over'));
                    target.addEventListener('drop',event=>{event.preventDefault();target.classList.remove('is-over');place(target.dataset.target,event.dataTransfer.getData('text/plain')||dragged)});
                    target.addEventListener('click',()=>{if(selected)place(target.dataset.target,selected);else if(placements[target.dataset.target])remove(target.dataset.target)});
                });
                question.querySelector('.drag-reset').addEventListener('click',()=>{Object.keys(placements).forEach(target=>delete placements[target]);selected=null;render()});
                render();
            });
            document.addEventListener('visibilitychange',()=>{timerPaused=document.hidden;persistSession();renderClock()});window.addEventListener('pagehide',()=>{persistSession()});form?.addEventListener('submit',event=>{if(!submittedByTimer)persistSession();form.querySelectorAll('button[type="submit"]').forEach(button=>{button.disabled=true;button.innerHTML='<i class="las la-spinner la-spin"></i> @lang('Submitting...')'})});refresh();
        })(jQuery);
    </template>
@endpush
