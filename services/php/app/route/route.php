<?php
use think\facade\Route;

// ---------- Auth（无需认证）----------
Route::get('admin/auth/captcha', 'admin/Auth/captcha');
Route::post('admin/auth/login', 'admin/Auth/login');
Route::post('admin/auth/logout', 'admin/Auth/logout');
Route::get('admin/auth/info', 'admin/Auth/info');

// ---------- 需要认证 + 操作日志的路由 ----------
Route::group('admin', function () {
    // Admin
    Route::get('admin/list', 'admin/Admin/index');
    Route::post('admin/add', 'admin/Admin/save');
    Route::put('admin/edit', 'admin/Admin/update');
    Route::delete('admin/delete', 'admin/Admin/delete');

    // Role
    Route::get('role/list', 'admin/Role/index');
    Route::post('role/add', 'admin/Role/save');
    Route::put('role/edit', 'admin/Role/update');
    Route::delete('role/delete', 'admin/Role/delete');

    // User
    Route::get('user/list', 'admin/User/index');
    Route::get('user/export', 'admin/User/export');
    Route::post('user/import', 'admin/User/import');
    Route::post('user/add', 'admin/User/save');
    Route::put('user/edit', 'admin/User/update');
    Route::delete('user/delete', 'admin/User/delete');

    // Menu
    Route::get('menu/list', 'admin/Menu/index');
    Route::post('menu/add', 'admin/Menu/save');
    Route::put('menu/edit', 'admin/Menu/update');
    Route::delete('menu/delete', 'admin/Menu/delete');

    // DictType
    Route::get('dict_type/list', 'admin/DictType/index');
    Route::post('dict_type/add', 'admin/DictType/save');
    Route::put('dict_type/edit', 'admin/DictType/update');
    Route::delete('dict_type/delete', 'admin/DictType/delete');

    // DictData
    Route::get('dict_data/list', 'admin/DictData/index');
    Route::get('dict_data/items', 'admin/DictData/items');
    Route::post('dict_data/add', 'admin/DictData/save');
    Route::put('dict_data/edit', 'admin/DictData/update');
    Route::delete('dict_data/delete', 'admin/DictData/delete');

    // Attachment
    Route::get('attachment/list', 'admin/Attachment/index');
    Route::post('attachment/upload', 'admin/Attachment/upload');
    Route::delete('attachment/delete', 'admin/Attachment/delete');

    // LoginLog
    Route::get('login_log/list', 'admin/LoginLog/index');
    Route::delete('login_log/delete', 'admin/LoginLog/delete');

    // OperationLog
    Route::get('operation_log/list', 'admin/OperationLog/index');
    Route::delete('operation_log/delete', 'admin/OperationLog/delete');

    // Server
    Route::get('server/info', 'admin/Server/info');

    // Profile（个人中心，不检查 RBAC 权限：管理员只能操作自己的数据）
    Route::get('profile', 'admin/Profile/read');
    Route::post('profile/avatar', 'admin/Profile/avatar');
    Route::put('profile/password', 'admin/Profile/password');
    Route::put('profile', 'admin/Profile/update');
})->middleware(['app\admin\middleware\Auth', 'app\admin\middleware\OperationLog']);
