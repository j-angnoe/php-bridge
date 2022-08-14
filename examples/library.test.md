# PHPBridge Library mode

Library modus betekent dat je phpbridge
als composer dependency aan je project heb toegevoegd
aldaar shit wilt serveren:

```php export=appje/router.php
<?php
ini_set('display_errors','on');
require_once __DIR__ .'/../vendor/autoload.php';

/* @insert server_mounts */
switch ($_SERVER['REQUEST_URI']) {
	case '/apps/my_spa.php':
		$server = new PhpBridge\Server('appje/apps/my_spa.php','/apps/my_spa.php');
		$server->dispatch();
	break;
	/* @insert app_router_other_cases */
	default:
		echo 'REQUEST_URI = ' . $_SERVER['REQUEST_URI'] . '<br>';
		echo 'Welkom in mijn bestaande app.';
}
```

```html export=appje/apps/my_spa.php
<?php
class MyController {
	function sayHello($a, $b) { 
		return (__METHOD__ . ' has been called, sum = ' . ($a+$b));
	}
}
bridge(MyController::class);
?>
<h1>Dit is mijn pagina</h1>
<button 
	id="testButton" 
	onclick="
		MyController.sayHello(1,2)
		.then(
				text => document.body.innerHTML += `<hr><div>${text}</div>`
		)"	  
>Click here</button>
```

Start een webservertje op 9995
```sh++
cd __DATA__ && php -S localhost:9995 '__DATA__/appje/router.php';
| daemon(['port' => 9995])
```

Kijk of we beeld hebben
```sh++
iframe('http://localhost:9995');
| assert({
	$this->see('Welkom in mijn bestaande app');
})
```



Open my_spa zo simpel mogelijk:
```sh++
iframe('http://localhost:9995/apps/my_spa.php');
| assert({
	$this->see('Dit is mijn pagina');
	$this->click('#testButton');
	$this->see('MyController::sayHello has been called');
	$this->see('sum = 3');
})
```


Nu doen we de my_spa maar maken we ook gebruik
van PhpBridge\Server::layout
```php append=app_router_other_cases
case '/apps/my_spa_with_layout.php';
	$server = new PhpBridge\Server('appje/apps/my_spa.php', '/apps/my_spa_with_layout.php');
	$server->layout(function($content) {
		$content = str_replace('h1','h3', $content);
		$content = '<h1>My App > Apps</h1><hr>' . $content;
		$content = PhpBridge\Utils::autoCompleteHtmlDocument($content);
		$content = PhpBridge\Utils::inject('head', '<style>html, body { font-family: Arial,Helvetica,Sans; }</style>', $content);
		return $content;
	});
	$server->dispatch($_SERVER['REQUEST_URI']);
break;
```

```sh++
iframe('http://localhost:9995/apps/my_spa_with_layout.php');
| assert({
	$this->see('My App > Apps');
	$this->see('Dit is mijn pagina');
	$this->click('#testButton');
	$this->see('MyController::sayHello has been called');
	$this->see('sum = 3');
})
```

Nu gaan we kijken of we een layer 
kunnen gebruiken:
```php append=app_router_other_cases
	case '/apps/vueblocks';
		$server = new PhpBridge\Server('appje/apps/vueblocks/','/apps/vueblocks');
		$server->layer('vue-blocks');
		$server->dispatch();
  	break;
```

```html export=appje/apps/vueblocks/index.html
<?php
bridge(Controller::class);
/* @insert vueblocks_extra */
class Controller {
	function doeIets($a,$b) {
		return ['som' => ($a+$b)];
	}
}
?>
<template component="xxx">
	<div>
		{{ title }}<br>
		<button id="testButton" @click="run()">Klik hier</button>
	</div>
	<script>
		return class vue {
			title = '';
			mounted() {
				this.title = ['Vue','component','xxx','was','mounted'].join(' ');
			}
			async run() { 
				var result = await Controller.doeIets(3,4);
				document.body.innerHTML += `<hr><div>${JSON.stringify(result)}</div>`			 
			}
		}
	</script>
</template>	
```

```sh++
iframe('http://localhost:9995/apps/vueblocks')
| assert({
	$this->see('Vue component xxx was mounted');
	$this->click('#testButton');
	$this->see('{"som":7}');
})
```

```html export=appje/apps/vueblocks/_layers/mylayer/layout.php
<h1>Mijn layout</h1>
<hr>
<?= $content ?>
```

Als je layers meestuurt moeten ze wel geactiveerd zijn.
Activeren gaat of zo:

```php append=vueblocks_extra
$this->layer('mylayer');
```

```sh++
iframe('http://localhost:9995/apps/vueblocks')
```


Activeren kan ook op een andere manier:
Even een nieuwe app opzetten:

```html export=appje/apps/vueblocks2/index.html
<template component="app">
	<div v-text="'Vue ' + 'Blocks 2'"></div>
</template>
<p>HTML Content hier</p>

```
```html export=appje/apps/vueblocks2/_layers/mylayer/layout.php
<h1>Mijn layout</h1>
<hr>
<?= $content ?>
```

```php append=app_router_other_cases
	case '/apps/vueblocks2';
		$server = new PhpBridge\Server('appje/apps/vueblocks2/','/apps/vueblocks2');
		$server->dispatch();
  	break;
```

```sh++
iframe('http://localhost:9995/apps/vueblocks2')
| assert({
	# vue-blocks layer is nog niet geactiveerd dus we zien
	# alleen de html content.
	$this->see('HTML Content hier');
	$this->dontSee('Vue Blocks 2');
	$this->dontSee('Mijn layout');
})
```

```json export=appje/apps/vueblocks2/package.json
{
	"layers" : ["vue-blocks", "mylayer"]
}
```

```sh++
iframe('http://localhost:9995/apps/vueblocks2')
| assert({
	# vue-blocks layer is nog niet geactiveerd dus we zien
	# alleen de html content.
	$this->see('Vue Blocks 2');
	$this->see('Mijn layout');
})
```

## NIeuew phpbridge\mount om het makkelijker te maken
Phpbridge\mount() werkt een beetje hetzelfde als Route::get()
in laravel.

```php append=server_mounts
PhpBridge::mount('/apps/spa_mount', function () {
	$this->path('appje/apps/my_spa.php');
	$this->layout(function($content) { 
		// return $content;
		return '<template url="/"><div>'.$content.'</div></template>';
	});
	$this->layer('vue-harness');
});
```

```sh++
iframe('http://localhost:9995/apps/spa_mount')
| assert({
	$this->see('pipeline-project');
	$this->see('Dit is mijn pagina');
	$this->click('#testButton');
	$this->see('MyController::sayHello has been called');
	$this->see('sum = 3');
})
```

## Anon support
```php append=server_mounts
PhpBridge::mount('/apps/anons/vue-harness','anons/index.php', function () {	
	$this->layer('classic-harness');
});
PhpBridge::mount('/apps/anons','anons/index.php');
```

```php export=anons/index.php
<?php
	echo "PHP is running.";
bridge('my_named_function', function () {
	return 'my_named_function'. ' was'. ' called';
});
?>
<script>
	var ctx = window.api || window;

	ctx.my_named_function().then(text => document.body.innerHTML += '<div>' + text + '</div>');

	var my_anon = <?= anon(function() {
		return 'my_anon ' . ' was called';
	}) ?>;
	my_anon().then(text => document.body.innerHTML += '<div>' + text + '</div>');
</script>
```

Kijk of bridge(fn) en anon(fn) goed werken in 
zonder layers:
```sh++
iframe('http://localhost:9995/apps/anons')
| assert({
	# bridge('my_fn', function() {...}) werkt:
	$this->see('my_named_function was called');
	
	# <?=anon(function....) werkt
	$this->see('my_anon was called');
})
```
Kijken of het werkt met vue-harness als layer
```sh++
iframe('http://localhost:9995/apps/anons/vue-harness')
| assert({
	# bridge('my_fn', function() {...}) werkt:
	$this->see('my_named_function was called');
	
	# <?=anon(function....) werkt
	$this->see('my_anon was called');
})
```