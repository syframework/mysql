# sy/mysql

MySQL database layer

## Installation

Install the latest version with

```bash
$ composer require sy/mysql
```

## Basic Usage

For production, a good idea is to store database connection settings in .ini files with restricted access. For example, *my_setting.ini*:

```
host = 127.0.0.1
dbname = my_database
username = my_username
password = my_password
```

```php
<?php

use Sy\Db\MySql\Crud;

$crud = new Crud('t_user');
$crud->setConfig(parse_ini_file('my_setting.ini'));
$user = $crud->retrieve(['id' => 3]);
```