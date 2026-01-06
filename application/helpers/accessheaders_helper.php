<?php
if (!function_exists('getallheaders'))
{
    function getallheaders()
    {
        $headers = [];
       foreach ($_SERVER as $name => $value)
       {
           if (substr($name, 0, 5) == 'HTTP_')
           {
               $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;

               if(isset($headers['Smemberid']) && !empty($headers['Smemberid'])){
                $headers['SmemberID'] = $headers['Smemberid'];
               }
               if(isset($headers['Sadminid']) && !empty($headers['Sadminid'])){
                $headers['SadminID'] = $headers['Sadminid'];
               }
               if(isset($headers['SadminID']) && !empty($headers['SadminID'])){
                $headers['SadminID'] = $headers['SadminID'];
               }
               if(isset($headers['Token']) && !empty($headers['Token'])){
                $headers['token'] = $headers['Token'];
               }
               if(isset($headers['token']) && !empty($headers['token'])){
                $headers['token'] = $headers['token'];
               }
           }
       }
       return $headers;
    }
    
}
function getallheaders2()
    {
       $headers = [];
       foreach ($_SERVER as $name => $value)
       {
           if (substr($name, 0, 5) == 'HTTP_')
           {
               $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;

               if(isset($headers['Smemberid']) && !empty($headers['Smemberid'])){
                $headers['SmemberID'] = $headers['Smemberid'];
               }
               if(isset($headers['Sadminid']) && !empty($headers['Sadminid'])){
                $headers['SadminID'] = $headers['Sadminid'];
               }
               if(isset($headers['SadminID']) && !empty($headers['SadminID'])){
                $headers['SadminID'] = $headers['SadminID'];
               }
               if(isset($headers['Token']) && !empty($headers['Token'])){
                $headers['token'] = $headers['Token'];
               }
               if(isset($headers['token']) && !empty($headers['token'])){
                $headers['token'] = $headers['token'];
               }
           }
       }
       return $headers;
    }
?>