<?php
/**
 * This file is part of php-simple-di.
 *
 * php-simple-di is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * php-simple-di is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with php-simple-di.  If not, see <http://www.gnu.org/licenses/>.
 */

$loader = require __DIR__ . '/../vendor/autoload.php';
$loader->addPsr4('Tests\\', __DIR__ . '/unit');
$loader->addPsr4('Fixtures\\', __DIR__ . '/fixtures');
