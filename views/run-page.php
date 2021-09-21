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
</style>
<script type="text/javascript">
var PHASE_POLL_INTERVAL = 1000;

jQuery(document).ready(function($){
    function responseErrorHandler( jqXHR, textStatus, errorThrown ) {
        $("#wp2static-spinner").removeClass("is-active");
        $("#wp2static-run" ).prop('disabled', false);

        console.log(errorThrown);
        console.log(jqXHR.responseText);

        alert(`${jqXHR.status} error code returned from server.
Please check your server's error logs or try increasing your max_execution_time limit in PHP if this consistently fails after the same duration.
More information of the error may be logged in your browser's console.`);
    }

    $( "#wp2static-run" ).click(function() {
        $("#wp2static-spinner").addClass("is-active");
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

        var offset = $("#wp2static-offset").val();
        var limit = $("#wp2static-limit").val();
        if (offset !== '' && limit !== ''){
            run_data['offset'] = parseInt(offset,10);
            run_data['limit'] = parseInt(limit,10);
        }else{
            delete run_data['offset'];
            delete run_data['limit'];
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: run_data,
            timeout: 0,
            success: function() {
                $("#wp2static-spinner").removeClass("is-active");
                $("#wp2static-run" ).prop('disabled', false);
            },
            error: responseErrorHandler
        });

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
    function pollPhases(){
        $.post(ajaxurl, {
            dataType: 'json',
            action: 'wp2static_poll_phases',
            startRow: 0,
            security: '<?php echo $run_nonce; ?>',
        }, function(response) {
            try {
                showPhaseResult(response);
            }catch(err){
                console.log(err);
            }
            setTimeout(pollPhases,PHASE_POLL_INTERVAL);
        });
    }
    pollPhases();

    // poll normal logs on refresh logs
    $( "#wp2static-poll-logs" ).click(function() {
        $("#wp2static-poll-logs" ).prop('disabled', true);
        $.post(ajaxurl, {
            dataType: 'json',
            action: 'wp2static_poll_log',
            startRow: 0,
            security: '<?php echo $run_nonce; ?>',
        }, function(response) {
            // map response to lines
            var responseText = response.map(function(e){ return e.time+": "+e.log+"\n"}).reduce(function(a,b){ return a + b},"");
            $('#wp2static-run-log').val(responseText);
            $("#wp2static-poll-logs" ).prop('disabled', false);
        });
    });

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

    <select id="wp2static-stage">
        <option value="null">All stages</option>
        <option value="1">URL detection</option>
        <option value="2">Crawl</option>
        <option value="3">Post process</option>
        <option value="4">Deploy</option>
        <option value="5">Post deploy action</option>
    </select>
    <input type="text" placeholder="offset" id="wp2static-offset">
    <input type="text" placeholder="limit" id="wp2static-limit">
    <button class="button button-primary" id="wp2static-run">Generate static site</button>

    <div id="wp2static-spinner" class="spinner" style="padding:2px;float:none;"></div>
    <br>

    <button class="button" id="wp2static-poll-logs">Refresh logs</button>
    <button class="button" id="wp2static-clear-logs">Clear logs</button>
    <br>
    <br>
    <textarea id="wp2static-run-log" rows=30 style="width: calc(100% - 200px)">
    Logs will appear here on completion or click "Refresh logs" to check progress
    </textarea>
</div>
