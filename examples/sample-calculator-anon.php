<?php

bridge('my_func', function() { 
    return 'my_func was called.';
});
?>
<div>
    Het resultaat:
    <script>
    var performCalculation = <?= anon(function ($a, $b) {
        return ['result' => $a+$b];
    }) ?>;

    var displayResult = result => {
        var div = document.createElement('div');
        if (typeof result == 'string') {
            div.innerHTML = result;
        } else { 
            div.innerHTML = JSON.stringify(result,null,3);
        }
        document.body.appendChild(div);
    }
    performCalculation(1,3).then(displayResult);
    my_func().then(displayResult);


    </script>
</div>
