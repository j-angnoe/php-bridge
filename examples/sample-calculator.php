<?php
class SampleCalculator { 
    function calc($a, $b) { 
        return [
            'result' => $a + $b
        ];
    }
}
bridge(SampleCalculator::class);
?>
<div>
    Het resultaat:
    <script>
    SampleCalculator.calc(1,3).then(result => {
        var div = document.createElement('div');
        div.innerHTML = JSON.stringify(result,null,3);
        document.body.appendChild(div);
    })
    </script>
</div>
