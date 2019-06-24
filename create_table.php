<?php

function createTable($objectDB, $Quotes, $tableName)
{
    $result = false;
    $query = 'CREATE TABLE `default`.' . $tableName . ' (';
    if (!empty($Quotes) && is_array($Quotes)) {
        foreach ($Quotes as $key => $element) {
            $query .= 'ask' . $element['symbol'] . ' String, ';
            $query .= 'bid' . $element['symbol'] . ' String, ';
        }
        $query .= 'dat Date, ti DateTime, `id` UInt16) ENGINE = MergeTree(dat, (id, dat), 8124);';
        try {
            $result = $objectDB->execute($query);
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
    return $result;
}

function writeQuotes($objectDB, $Quotes, $tableName)
{
    $columns = '';
    $wdata = [];
    $data = '';
    $ind = 1;

    if (!empty($Quotes) && is_array($Quotes)) {
        foreach ($Quotes as $key => $element) {
            $columns .= 'ask' . $element['symbol'] . ', ';
            $columns .= 'bid' . $element['symbol'] . ', ';

            $data .= $element['ask'] . ',';
            $data .= $element['bid'] . ',';
            $ind++;
            //if($ind > 553) break; //костыль
        }
        $columns .= 'dat,ti';
        $data .= date('Y-m-d').",".time();
        $columns = explode(',', $columns);
        $data = explode(',', $data);
        $wdata[0] = $data;

        try {
            $result = $objectDB->insert($tableName, $columns, $wdata);
        }
        catch(Exception $e){
            $er = $e->getMessage();
            setColumn($objectDB, getMissingColumn($er), $tableName);
        }
    }
    return $result;
}

function getMissingColumn($textErr)
{
    preg_match_all('/(column)( ).*?( )(in)/', $textErr, $match);
    $result = $match[0][0];
    $result = str_replace('column ', '', $result);
    $result = str_replace(' in', '', $result);
    return $result;
}

function setColumn($objectDB, $column, $tableName)
{
    try {
        $result = $objectDB->execute('ALTER TABLE `default`.' . $tableName . ' ADD COLUMN ' . $column . ' String AFTER ti');
    } catch (Exception $e) {
        return $e->getMessage();
    }
    return $result;
}