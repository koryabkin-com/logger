# class **new Logger()**
A class that allows for a convenient debugging system for your PHP applications.



How to add a class in a PHP project file:
```
include_once(__DIR__.'/logger.php');
$logger = !empty($logger) ? $logger : new Logger();
```
