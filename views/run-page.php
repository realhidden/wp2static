<?php
namespace WP2Static;
// phpcs:disable Generic.Files.LineLength.MaxExceeded                              
// phpcs:disable Generic.Files.LineLength.TooLong                                  

$run_nonce = wp_create_nonce( 'wp2static-run-page' );
include_once(__DIR__.'/../src/WsLog.php');
?>
<style>
    .wp2static-progress{
        width:170px;
        float:left;
        padding-right:30px;
    }
    .wp2static-progress h3{
        font-size: 14px;
        margin-bottom: 0;
    }
    .wp2static-progress .time{
        font-size: 10px;
        color:#444;
    }

    .wp2static-progress progress{
        width:100%;
    }
    .wp2static-progress .progress div{
        font-weight: bold;
        font-size: 10px;
        text-align: center;
    }
    #wp2static-run{
        width:calc(100% - 200px);
    }

    #wp2static-error{
        width:calc(100% - 225px);
        margin-left: 200px
    }
</style>
<script type="text/javascript">
var PHASE_POLL_INTERVAL = 3000;
var RETRY_DELAY = 15000;
var STUCK_LIMIT = PHASE_POLL_INTERVAL * 3 + 5000;
var phase_update_timeout = 0;
var retry_timeout = 'notimeout';

jQuery(document).ready(function($){

    function startProcess(run_data){
        $("#wp2static-error").hide();
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: run_data,
            timeout: 0,
            success: function() {
                $("#wp2static-run" ).prop('disabled', false);
            },
            error: console.log
        });
    }

    $("#wp2static-error-cancel").click(function(e){
        e.preventDefault();
        $("#wp2static-error").hide();
        clearTimeout(retry_timeout);
    });


    $( "#wp2static-run" ).click(function() {
       $("#wp2static-run" ).prop('disabled', true);

        var run_data = {
            action: 'wp2static_run',
            security: '<?php echo $run_nonce; ?>',
        };

        var stage = $("#wp2static-stage").val();
        if (stage === 'null') {
            delete run_data['stage'];
        }else{
            run_data['stage'] = parseInt(stage,10);
        }
        if ($("#wp2static-stage-only").prop('checked')){
            run_data['only-stage'] = "1";
        }else{
            delete run_data['only-stage'];
        }

        var offset = $("#wp2static-offset").val();
        var limit = $("#wp2static-limit").val();
        if (offset !== '' && limit !== ''){
            run_data['offset'] = parseInt(offset,10);
            run_data['limit'] = parseInt(limit,10);
        }else{
            delete run_data['offset'];
            delete run_data['limit'];
        }

        startProcess(run_data);

    });

    // poll phases
    function showPhaseResult(result) {
        var phases = $(".wp2static-progress .phase");
        phases.each(function (i, item) {
            var p = $(item);
            var phase = p.data('phase');
            if (!result[phase]) {
                p.find(".time").text("");
                p.find("input").prop('checked',false);
                p.find(".progress").css({opacity:0});
                return;
            }
            var r = result[phase];
            p.find(".time").text(r.time);
            p.find("input").prop('checked',r.finished);
            if (!r.progress){
                p.find(".progress").css({opacity:0});
                return;
            }
            var progress = r.progress.split("/");
            if (progress.length !== 2) {
                return;
            }
            // we have progress
            p.find(".progress").css({opacity:1});
            p.find(".progress progress").val(parseInt(progress[0],10)).prop('max',progress[1]);
            p.find(".progress div").text(r.progress);
        });
    }

    function getPhaseListReverse(){
        var phaseList = [];
        var phases = $(".wp2static-progress .phase");
        phases.each(function (i, item) {
            phaseList.push($(item).data('phase'));
        });
        // first and last items are irrelevant
        phaseList = phaseList.slice(1,phaseList.length-1);
        phaseList.reverse();
        return phaseList;
    }

    function showTimeoutError(message, retryData = false){
        // show message
        $("#wp2static-error").show();
        $("#wp2static-error p").text(message);

        if (retryData === false){
            return;
        }
        // timeout for retry
        clearTimeout(retry_timeout);
        console.log(retryData);
        retry_timeout = setTimeout(()=>{
            startProcess(retryData);
            retry_timeout = 'notimeout';
        },RETRY_DELAY);
    }

    var stuckTime = 0;
    var lastLine = '';

    function checkStuck(phases, loglines) {
        // we found the deploy end, the deploy is finished
        if (phases['WPSTATIC_PHASE_MARKERS_DEPLOYEND']) {
            return;
        }
        // if we do not find a deploy start, never started
        if (!phases['WPSTATIC_PHASE_MARKERS_DEPLOYSTART']) {
            return;
        }

        // already showing message + retrying
        if (retry_timeout !== 'notimeout'){
            return;
        }

        var stuckForSure = false;
        if (loglines.length > 0) {
            if (loglines[0].time !== lastLine.time) {
                lastLine = loglines[0];
                stuckTime = new Date().getTime();
            } else {
                console.log("Might be stuck");
                if (new Date().getTime() - stuckTime > STUCK_LIMIT) {
                    // we are really stuck
                    stuckForSure = true;
                }
            }
        }
        // just to make sure
        if (!stuckForSure){
            return;
        }

        // find phase
        var phaseList = getPhaseListReverse();
        var lastPhase = false;
        var lastPhaseId = 0;
        for (var i = 0; i < phaseList.length; i++) {
            if (phases[phaseList[i]]) {
                lastPhase = phaseList[i];
                lastPhaseId = phaseList.length - i;
                break;
            }
        }

        var lastPhaseData = phases[lastPhase];
        // if we don't have a lastPhase, we have a problem we cannot handle
        if (lastPhaseData === false) {
            showTimeoutError('Deployment might be stuck, but we cannot tell in which phase!');
            return;
        }

        // let's check if lastPhase is finished, we don't know what to do
        if (lastPhaseData.finished) {
            showTimeoutError('Deployment might be stuck, but the last phase is still finished!');
            return;
        }

        // no process to continue
        if (!lastPhaseData.progress) {
            showTimeoutError('Deployment might be stuck, last phase does not have a progress!');
            return;
        }

        // continue the progress from the last item
        var p = lastPhaseData.progress.split("/",2);
        showTimeoutError('We are stuck at ' + lastPhase + ' progress: ' + lastPhaseData.progress,{
            action: 'wp2static_run',
            security: '<?php echo $run_nonce; ?>',
            stage: lastPhaseId,
            offset: parseInt(p[0],10),
            limit: Math.max(parseInt(p[1],10),50000)
        });
    }

    function pollPhases(){
        $.post(ajaxurl, {
            dataType: 'json',
            action: 'wp2static_poll_phases',
            startRow: 0,
            security: '<?php echo $run_nonce; ?>',
        }, function(phaseResponse) {
            try {
                showPhaseResult(phaseResponse);
            }catch(err){
                console.log(err);
            }

            $.post(ajaxurl, {
                dataType: 'json',
                action: 'wp2static_poll_log',
                startRow: 0,
                security: '<?php echo $run_nonce; ?>',
            }, function(response) {
                // map response to lines
                var responseText = response.map(function(e){ return e.time+": "+e.log+"\n"}).reduce(function(a,b){ return a + b},"");
                $('#wp2static-run-log').val(responseText);
                if (phase_update_timeout !== 'dontupdate') {
                    phase_update_timeout = setTimeout(pollPhases, PHASE_POLL_INTERVAL);

                    // check if its stuck
                    checkStuck(phaseResponse,response);
                }
            });
        });
    }
    pollPhases();
    document.addEventListener('visibilitychange', function(){
        if (document.hidden === true){
            clearTimeout(phase_update_timeout);
            phase_update_timeout = 'dontupdate';
        }else{
            phase_update_timeout = setTimeout(pollPhases,0);
        }
    })

    $( "#wp2static-clear-logs" ).click(function() {
        $("#wp2static-clear-logs" ).prop('disabled', true);
        $.post(ajaxurl, {
            dataType: 'text',
            action: 'wp2static_clear_log',
            security: '<?php echo $run_nonce; ?>',
        }, function(response) {
            $('#wp2static-run-log').val(response);
            $("#wp2static-clear-logs" ).prop('disabled', false);
        });
    });
});
</script>

<div class="wrap">
    <div class="wp2static-progress">
        <?php
        $names = array(
            WPSTATIC_PHASE_MARKERS::DEPLOY_START => "Deploy start",
            WP2STATIC_PHASES::URL_DETECT => "Url detection",
            WP2STATIC_PHASES::CRAWL => "Crawl",
            WP2STATIC_PHASES::POST_PROCESS => "Post process",
            WP2STATIC_PHASES::DEPLOY => "Deploy",
            WP2STATIC_PHASES::POST_DEPLOY => "Post deploy",
            WPSTATIC_PHASE_MARKERS::DEPLOY_END => "Deploy finished",
        );
        foreach ($names as $key=>$name){
            ?>
            <div class="<?=$key?> phase" data-phase="<?=$key?>">
                <h3><input type="checkbox" disabled/><?=$name?></h3>
                <span class="time"></span>
                <div class="progress" style="opacity: 0;">
                    <progress value="0" max="0"></progress>
                    <div></div>
                </div>
            </div>
        <?php } ?>
    </div>
    <div class="error notice" id="wp2static-error" style="display: none">
        <p></p>
        <button id="wp2static-error-cancel" class="button">Cancel retry</button><br/><br/>
    </div>
    <button class="button button-primary" id="wp2static-run">Generate static site</button>
    <hr/>
    <select id="wp2static-stage">
        <option value="null">All stages</option>
        <option value="1">URL detection</option>
        <option value="2">Crawl</option>
        <option value="3">Post process</option>
        <option value="4">Deploy</option>
        <option value="5">Post deploy action</option>
    </select>
    <label for="wp2static-stage-only">
        <input type="checkbox" id="wp2static-stage-only" name="wp2static-stage-only">
        Only one stage
    </label>
    <input type="text" placeholder="offset" id="wp2static-offset" style="width:70px">
    <input type="text" placeholder="limit" id="wp2static-limit" style="width:70px">
    <button class="button" id="wp2static-clear-logs">Clear logs</button>
    <hr/>
    <textarea id="wp2static-run-log" rows=30 style="width: calc(100% - 200px)">
    </textarea>
</div>
