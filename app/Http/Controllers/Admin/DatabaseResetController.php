<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Brian2694\Toastr\Facades\Toastr;

class DatabaseResetController extends Controller
{
    public function resetAutoIncrement()
    {
        try {
            // Get all tables in the database
            $tables = DB::select('SHOW TABLES');
            
            // Get the first table object to determine the property name
            if (empty($tables)) {
                Toastr::info('No tables found in the database.');
                return back();
            }
            
            // Get the property name dynamically from the first table object
            $tableObj = $tables[0];
            $propertyName = array_keys(get_object_vars($tableObj))[0];
            
            foreach ($tables as $table) {
                $tableName = $table->$propertyName;
                
                // Get table status to check if it has auto_increment
                $tableStatus = DB::select("SHOW TABLE STATUS LIKE '{$tableName}'");
                
                if (!empty($tableStatus) && $tableStatus[0]->Auto_increment !== null) {
                    // Reset auto_increment to 1
                    DB::statement("ALTER TABLE {$tableName} AUTO_INCREMENT = 1");
                }
            }
            
            Toastr::success('Auto-increment values have been reset successfully!');
            return back();
        } catch (\Exception $e) {
            Toastr::error('Error resetting auto-increment values: ' . $e->getMessage());
            return back();
        }
    }
}