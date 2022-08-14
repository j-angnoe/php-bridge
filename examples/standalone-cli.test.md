# Testing the phpbridge cli

## Basics
We maken een simpele php applet aan met een knop
er in waar we op kunnen klikken.
Als je op de knop klikt wordt de sayHello methode
van MyController aangeroepen en het resultaat ervan
wordt geretourneerd. 
```html export=index.php
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
		  onclick="MyController.sayHello(1,2).then(text => document.body.innerHTML += `<hr><div>${text}</div>`)"	  
>Click here</button>
```

```html export=second.php
<?php
// Om MyController te laden zonder de HTML:
// ob_start(); require 'index.php'; ob_get_clean()

bridge(MyController::class)
	->require('index.php')
;
?>
<h1>Pagina nr 2</h1>
<button 
	id="testButton" 
		  onclick="MyController.sayHello(1,2).then(text => document.body.innerHTML += `<hr><div>${text}</div>`)"	  
>Click here</button>
```


Verifier of het bestand op de juiste plek staat
en verifier even wat vereiste parameters voor 
de volgende testen:
```sh++
cat '__DATA__/index.php'
| assert({
	# Titeltje om te zien dat we de juiste pagina 
	# in beeld hebben
	$this->contains('Dit is mijn pagina');
	# Deze id hebben we nodig om onze click te targetten
	$this->contains('id="testButton"');
	# Er moet een som weggeschreven worden 
	# Om argument-transport te verifieren.
	$this->contains('sum =');
})
```
```sh++
echo __DATA__
```
Open phpbridge in `directory` modus:
```sh++
./cli/phpbridge -f __DATA__ -p 9999
| daemon(['port' => 9999])
```

Open de pagina door, de index pagina zou 
automatisch geopend moeten worden:
Voer vervolgens wat inhoudelijke checks uit.
```sh++
iframe('http://localhost:9999')
| assert({
	//return;
	# $this->js('alert("HOI")');
	$this->see('Dit is mijn pagina')
		->click('#testButton')
		->see('MyController::sayHello')
		->see('sum = 3')
	;
});
```

Als we nu naar index.php browsen dan is het hetzelfde
verhaal:
```sh++
iframe('http://localhost:9999/index.php')
| assert({
	//return;
	# $this->js('alert("HOI")');
	$this->see('Dit is mijn pagina')
		->click('#testButton')
		->see('MyController::sayHello')
		->see('sum = 3')
	;
});
```

## Single file mode
```sh++
./cli/phpbridge -f __DATA__ -p 9998 --no-browser
| daemon(['port' => 9998])
```
```sh++
iframe('http://localhost:9998')
| assert({
	$this->see('Dit is mijn pagina')
		->click('#testButton')
		->see('MyController::sayHello')
		->see('sum = 3')
	;
});
```

# phpbridge --layer optie

```html export=mylayer/layout.php
<html>
<style>
body { background: lightblue; }
</style>
<body>
	<nav>
		Menu: 
		<?php foreach (glob('*.php') as $file): ?>
		<a href="<?= $file ?>"><?=$file?></a> 
		<?php endforeach; ?>
	</nav>
	

mylayer/layout before:
<?= $content ?>
mylayer/layout after:
</body>
</html>
```

```sh++ 
./cli/phpbridge -f __DATA__ \
	-p 9997 \
	--no-browser \
	--layer '__DATA__/mylayer' \
| daemon(['port' => 9997])
```

```sh++
iframe('http://localhost:9997')
| assert({
	$this->see('mylayer/layout before');
	$this->see('mylayer/layout after');
	$this->click('#testButton');
	$this->see('MyController::sayHello');
})
```

```sh++
iframe('http://localhost:9997/second.php')
| assert({
	$this->dontSee('Dit is mijn pagina');
	$this->see('Pagina nr 2');
});
```

# Aanwezigheid van autoload.php
Autoloads in layer bestanden worden
automatisch ingeladen.
```file export=mylayer/autoload.php
<?php
echo 'mylayer/autoload.php was loaded<br>';
```

En ook autoloads in de map
die ge-phpbridged wordt:
```file export=autoload.php
<?php
echo 'main/autoload.php was loaded<br>';
```

```sh++
iframe('http://localhost:9997/second.php')
| assert({
	$this->see('main/autoload.php was loaded');
	$this->see('mylayer/autoload.php');
});
```

## Serve static files
```file export=static1.txt
Dit is static1.txt
```

Non php/html bestanden worden geserveerd

```sh++ 
iframe('http://localhost:9997/static1.txt')
| assert({
	$this->see('Dit is static1.txt');
});

```

@FIXME - Je mag niet zomaar autoload aan kunnen roepen.
```sh++ 
iframe('http://localhost:9997/autoload.php')
| assert({
	$this->see('Dit is static1.txt');
});
```

Je kunt ook resources opvragen van een layer.
De naam van de laag is hierbij niet belangrijk
Dit heeft te maken met `gelaagdheid`.

```file export=mylayer/mylayerfile.txt
dit is mylayerfile.txt
```
```sh++ 
iframe('http://localhost:9997/mylayerfile.txt')
| assert({
	$this->see('dit is mylayerfile.txt');
});
```
