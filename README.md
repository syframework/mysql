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
use Sy\Db\MySql\Gate;

$crud = new Crud('t_user');
$crud->setDbGate(new Gate(parse_ini_file('my_setting.ini')));

// Create
$crud->create(['firstanme' => 'John', 'lastname' => 'Doe']);
$crud->createMany([
	['firstanme' => 'John', 'lastname' => 'Doe'],
	['firstanme' => 'John', 'lastname' => 'Wick'],
]);

// Retrieve
$user = $crud->retrieve(['id' => 3]);
$users = $crud->retrieveAll(['LIMIT' => 10]);
$users = $crud->retrieveAll(['LIMIT' => 10, 'OFFSET' => 10]);

// Update
$crud->update(['id' => 3], ['firstname' => 'Jane']);

// Delete
$crud->delete(['id' => 3]);
```