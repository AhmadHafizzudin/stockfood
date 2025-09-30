<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseResetController extends Controller
{
    public function resetAutoIncrement()
    {
        try {
            // Get all tables from the database
            $tables = DB::select('SHOW TABLES');
            $dbName = config('database.connections.mysql.database');
            $tableColumn = 'Tables_in_' . $dbName;
            
            $resetTables = [];
            
            foreach ($tables as $table) {
                $tableName = $table->$tableColumn;
                
                // Check if the table has an auto-increment column
                $columns = DB::select("SHOW COLUMNS FROM `{$tableName}` WHERE Extra = 'auto_increment'");
                
                if (count($columns) > 0) {
                    // Reset auto-increment to 1
                    DB::statement("ALTER TABLE `{$tableName}` AUTO_INCREMENT = 1");
                    $resetTables[] = $tableName;
                }
            }
            
            return back()->with('success', 'Auto-increment values have been reset for the following tables: ' . implode(', ', $resetTables));
        } catch (\Exception $e) {
            return back()->with('error', 'Error resetting auto-increment values: ' . $e->getMessage());
        }
    }
}