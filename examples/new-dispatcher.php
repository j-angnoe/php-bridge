<h1>We hebben beeld</h1>
<script>
var anon = <?= anon(function () {
    $generator = function () { 
        for ($i=0;$i< 10;$i++) {
            yield $i;
        }
    };

    $infinite = function () {
        $i=0;
        while(true) {
            $i++;
            yield $i;
        }
    };

    $iterator = new ArrayIterator([1,2,3,4,5]);

    
    return [
        // Example of what happens when you return a generator
        'generator' => $generator(),

        'iterator' => $iterator,

        'iterator_map' => iterator_map(function($value) {
            return $value * 2;
        }, $iterator),

        'iterator_map with infinite generator' => iterator_map(function($value, $key) {
            if ($key > 15) { 
                return iterator_stop();
            }
            return $value;
        }, $infinite()),

        'iterator_map with generator' => iterator_map(function($value) {
            return $value * 2;
        }, $generator()),

        'closure_result' => function() {
            return [1,2,3];
        },

        'closure_result iterator' => function () use ($iterator) {
            return $iterator;
        },

    ];
}) ?>

anon().then(result => {
    document.body.innerHTML += '<pre>' + JSON.stringify(result, null, 3) + '</pre>';
})
</script>

