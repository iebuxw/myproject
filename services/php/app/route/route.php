<?php
use think\facade\Route;

// ---------- Auth ----------
Route::post('admin/auth/login', 'admin/Auth/login');
Route::post('admin/auth/logout', 'admin/Auth/logout');
Route::get('admin/auth/info', 'admin/Auth/info');

// ---------- Admin ----------
Route::get('admin/admin/list', 'admin/Admin/index');
Route::post('admin/admin/add', 'admin/Admin/save');
Route::put('admin/admin/edit', 'admin/Admin/update');
Route::delete('admin/admin/delete', 'admin/Admin/delete');

// ---------- Role ----------
Route::get('admin/role/list', 'admin/Role/index');
Route::post('admin/role/add', 'admin/Role/save');
Route::put('admin/role/edit', 'admin/Role/update');
Route::delete('admin/role/delete', 'admin/Role/delete');

// ---------- User ----------
Route::get('admin/user/list', 'admin/User/index');
Route::post('admin/user/add', 'admin/User/save');
Route::put('admin/user/edit', 'admin/User/update');
Route::delete('admin/user/delete', 'admin/User/delete');

// ---------- Menu ----------
Route::get('admin/menu/list', 'admin/Menu/index');
Route::post('admin/menu/add', 'admin/Menu/save');
Route::put('admin/menu/edit', 'admin/Menu/update');
Route::delete('admin/menu/delete', 'admin/Menu/delete');
