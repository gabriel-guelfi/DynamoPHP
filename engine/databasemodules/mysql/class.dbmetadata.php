<?php
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
//                                                                                                                                                                //
//                                                                ** SPLIT PHP FRAMEWORK **                                                                       //
// This file is part of *SPLIT PHP Framework*                                                                                                                     //
//                                                                                                                                                                //
// Why "SPLIT"? Firstly because the word "split" is a reference to micro-services and split systems architecture (of course you can make monoliths with it,       //
// if that's your thing). Furthermore, it is an acronym for these 5 bound concepts which are the bases that this framework leans on, which are: "Simplicity",     //
// "Purity", "Lightness", "Intuitiveness", "Target Minded"                                                                                                        //
//                                                                                                                                                                //
// See more info about it at: https://github.com/gabriel-guelfi/split-php                                                                                         //
//                                                                                                                                                                //
// MIT License                                                                                                                                                    //
//                                                                                                                                                                //
// Copyright (c) 2022 SPLIT PHP Framework Community                                                                                                               //
//                                                                                                                                                                //
// Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to          //
// deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or         //
// sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:                            //
//                                                                                                                                                                //
// The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.                                 //
//                                                                                                                                                                //
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,FITNESS     //
// FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY           //
// WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.     //
//                                                                                                                                                                //
////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

class Dbmetadata
{

  private static $collection;
  private static $tableKeys;

  public static function initCache()
  {
    $p = INCLUDE_PATH . '/application/cache/';

    try {
      if (!file_exists($p)) {
        mkdir($p, 0755, true);
        touch($p);
        chmod($p, 0755);
      }
      if (!file_exists($p . 'database-metadata.cache')) {
        file_put_contents($p . 'database-metadata.cache', '');
      }
    } catch (Exception $ex) {
      System::log('sys_error', $ex->getMessage());
    }
  }

  public static function tbInfo($tablename, $updCache = false)
  {
    if (empty(self::$collection)) {
      self::$collection = self::readCache();
    }

    if (!isset(self::$collection[$tablename]) || $updCache) {
      $dblink = System::loadClass(INCLUDE_PATH . "/engine/databasemodules/" . DBTYPE . "/class.dblink.php", 'dblink');
      $sql = System::loadClass(INCLUDE_PATH . "/engine/databasemodules/" . DBTYPE . "/class.sql.php", 'sql');
      $res_f = $dblink->getConnection('reader')->runsql($sql->write("DESCRIBE `" . $tablename . "`", array(), $tablename)->output());

      $fields = array();
      $key = false;
      foreach ($res_f as $row) {
        $fields[] = $row;

        if ($row->Key === "PRI") {
          $key = (object) array(
            'keyname' => $row->Field,
            'keyalias' => $tablename . "_" . $row->Field
          );
        }
      }

      $res_r = $dblink->getConnection('reader')->runsql($sql->write("SELECT TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME, REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = '" . DBNAME . "' AND REFERENCED_TABLE_NAME = '" . $tablename . "';", array(), $tablename)->output());

      foreach ($res_r as $k => $v) {
        $res_r[$v->TABLE_NAME] = $v;
        unset($res_r[$k]);
      }

      self::$collection[$tablename] = array(
        'table' => $tablename,
        'fields' => $fields,
        'references' => $res_r,
        'key' => $key
      );

      $res_r = $dblink->getConnection('reader')->runsql($sql->write("SELECT TABLE_NAME,COLUMN_NAME,CONSTRAINT_NAME, REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA = '" . DBNAME . "' AND TABLE_NAME = '" . $tablename . "';", array(), $tablename)->output());

      foreach ($res_r as $k => $v) {
        $res_r[$v->REFERENCED_TABLE_NAME] = $v;
        unset($res_r[$k]);
      }

      self::$collection[$tablename]['relatedTo'] = $res_r;

      self::updCache();
    }

    return (object) self::$collection[$tablename];
  }

  public static function tbPrimaryKey(string $tablename)
  {
    if (!isset(self::$tableKeys[$tablename])) {
      $dblink = System::loadClass(INCLUDE_PATH . "/engine/databasemodules/" . DBTYPE . "/class.dblink.php", 'dblink');
      $sql = System::loadClass(INCLUDE_PATH . "/engine/databasemodules/" . DBTYPE . "/class.sql.php", 'sql');
      $res_f = $dblink->getConnection('reader')->runsql($sql->write("SHOW KEYS FROM `" . $tablename . "` WHERE Key_name = 'PRIMARY'", array(), $tablename)->output(true));

      self::$tableKeys[$tablename] = $res_f[0]->Column_name;
    }

    return self::$tableKeys[$tablename];
  }

  public static function listTables()
  {
    $dblink = System::loadClass(INCLUDE_PATH . "/engine/databasemodules/" . DBTYPE . "/class.dblink.php", 'dblink');
    $sql = System::loadClass(INCLUDE_PATH . "/engine/databasemodules/" . DBTYPE . "/class.sql.php", 'sql');
    $res = $dblink->getConnection('reader')->runsql($sql->write("SHOW TABLES")->output());

    $ret = array();
    $keyname = "Tables_in_" . DBNAME;
    foreach ($res as $t) {
      $ret[] = $t->$keyname;
    }

    return $ret;
  }

  private static function readCache()
  {
    try {
      return (array) unserialize(file_get_contents(INCLUDE_PATH . '/application/cache/database-metadata.cache'));
    } catch (Exception $ex) {
      System::log('sys_error', $ex->getMessage());
    }
  }

  private static function updCache()
  {
    $p = INCLUDE_PATH . '/application/cache/database-metadata.cache';

    try {
      return file_put_contents($p, serialize(array_merge(self::readCache(), self::$collection)));
    } catch (Exception $ex) {
      System::log('sys_error', $ex->getMessage());
    }
  }

  public static function clearCache()
  {
    try {
      unlink(INCLUDE_PATH . '/application/cache/database-metadata.cache');
    } catch (Exception $ex) {
      System::log('sys_error', $ex->getMessage());
    }

    self::initCache();
  }
}

// Dbmetadata::initCache();
