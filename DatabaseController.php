public function export(){

        $DbName = env('DB_DATABASE');
        $get_all_table_query = "SHOW TABLES ";
        $result = DB::select($get_all_table_query);
        
        $prep = "Tables_in_$DbName";
        foreach ($result as $res){
            $tables[] =  $res->$prep;
        }
        
        $connect = DB::connection()->getPdo();

        $get_all_table_query = "SHOW TABLES";
        $statement = $connect->prepare($get_all_table_query);
        $statement->execute();
        $result = $statement->fetchAll();
        $output = '';
        foreach($tables as $table)
        {
            $output .= "\nDROP TABLE IF EXISTS $table;";

            $show_table_query = "SHOW CREATE TABLE " . $table . "";
            $statement = $connect->prepare($show_table_query);
            $statement->execute();
            $show_table_result = $statement->fetchAll();

            foreach($show_table_result as $show_table_row)
            {
                $output .= "\n\n" . $show_table_row["Create Table"] . ";\n\n";
            }
            $select_query = "SELECT * FROM " . $table . "";
            $statement = $connect->prepare($select_query);
            $statement->execute();
            $total_row = $statement->rowCount();

            for($count=0; $count<$total_row; $count++)
            {
                $single_result = $statement->fetch(\PDO::FETCH_ASSOC);
                $table_column_array = array_keys($single_result);
                $table_value_array = array_values($single_result);
                $output .= "\nINSERT INTO $table (";
                $output .= "`" . implode("`, `", $table_column_array) . "`) VALUES (";
                // $output .= '"' . implode('", "', ['name'=> '', 'age'=> NULL]) . "\");\n";

                $table_value = array_map(function($item) {
                    return ($item == '')? 'NULL' : "\"$item\"";
                }, $table_value_array);

                $output .= '' . implode(', ', $table_value) . ");\n";
            }
        }

        $file_name = date('Y_F_d_').time().'_database_backup_on'.'.sql';
        $file_handle = fopen($file_name, 'w');
        fwrite($file_handle, $output);
        fclose($file_handle);
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($file_name));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_name));
        ob_clean();
        flush();
        readfile($file_name);
        unlink($file_name);
    }

    public function import(Request $request){

        $validator = Validator::make($request->all(), [
            'file' => 'required',
        ]);
        if($validator->passes()){
            try{
                if($request->hasFile('file') && $file = $request->file('file')){
                    if($file->getClientOriginalExtension() == 'sql'){
                        $connect = DB::connection()->getPdo();
                        $output = '';
                        foreach (file($file) as $key => $row)
                        {
                            $start_character = substr(trim($row), 0, 2);
                            if($start_character != '--' || $start_character != '/*' || $start_character != '//' || $row != '')
                            {
                                $output = $output . $row;
                                $end_character = substr(trim($row), -1, 1);
                                if($end_character == ';')
                                {
                                    $statement = $connect->prepare($output);
                                    $statement->execute();
                                    $output = '';
                                }
                            }
                        }
                        return response()->json(['status'=>true, 'message'=>'Sql File Successfully Uploaded.', 'data'=> $output]);
                    }
                    return response()->json(['status' => false, 'message' => 'Invalid sql file']);
                }
            }catch(\Exception $e){
                return response()->json(['status' => false, 'message' => '500 - Server-side error', 'error'=> $e->getMessage()]);
            }
        }
        return response()->json(['status' => false, 'message' => 'Please Input Sql File.', 'errors' => $validator->messages()]);
        
    }
