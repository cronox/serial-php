## Installation
add this line to your `composer.json` file:
```json
"cronox/serial-php": "^1.0"
```
or run
```sh
composer require cronox/serial-php
```

## A Simple Example

```php
$phone = '321321321';
$message = 'Test SMS'
try {
    $serial = new \Cronox\SerialPHP\SerialPHP();
    $serial->setSerialPort('/dev/ttyS0');
    $serial->openSerialPort();
    $serial->setBaudRate(9600);
    $serial->send('AT', 2);
    $serial->send('AT+CMGF=1', 2);
    $serial->send('AT+CMGS="'.$phone.'"', 2);
    $serial->send($message.chr(26), 4);
    return $serial->read();
} catch (\Exception $exception) {
    throw $exception;
}
```