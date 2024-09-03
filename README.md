# Yii2 Yookassa

Payment widget for Yookassa ([dvizh/yii2-order](https://github.com/dvizh/yii2-order))

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist sibds/yii2-yookassa "*"
```

or add

```
"sibds/yii2-yookassa": "*"
```

to the require section of your `composer.json` file.

## Usage

Once the extension is installed, simply use it in your code by :

```php
<?= \sibds\payment\yookassa\AutoloadExample::widget(); ?>
```

Support for events about the successful payment. Add the settings app.:

```php
// The event of successful payment
'on successPayment' => ['\frontend\controllers\ShopController', 'successPayment'],
```
