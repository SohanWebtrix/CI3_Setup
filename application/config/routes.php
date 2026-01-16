<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/

$route['default_controller'] = 'welcome';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

$route['users/list'] = 'core/users/Users/list';
$route['users/delete'] = 'core/users/Users/deleteUser';
$route['users/changeStatus'] = 'core/users/Users/changeStatus';
$route['user/(:num)'] = 'core/users/Users/userDetails/$1';
$route['changePassword'] = 'core/users/Users/confirm_password/';
$route['user/add'] = 'core/users/Users/userDetails';
$route['shop/add'] = 'core/users/Users/shopDetails';
$route['user/edit/(:num)'] = 'core/users/Users/userDetails/$1';
// $route['shop/add'] = 'core/shop/Shops/shopDetails';
// $route['shop/list'] = 'core/shop/Shops/list';
$route['shop/update']='core/shop/Shops/updateShop';
$route['formconfig/save'] = 'core/formapi/Forms/saveDefinition';
$route['form/submit'] = 'core/formapi/Forms/submit';
$route['formconfig/form/(:any)'] = 'core/formapi/Forms/getForm/$1';
$route['formconfig/form/(:any)/settings'] = 'core/formapi/Forms/updateSettings/$1';
$route['formconfig/forms/last'] = 'core/formapi/Forms/getLastForm';
$route['EmailsTemp/save'] = 'core/emailTemplate/EmailsTemp/saveEmail';

