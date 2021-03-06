<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BasicController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\IssueController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Models\Permission;
use App\Models\UserData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::get('/', function () {
//     return response('hello1');
// });
// Route::get('/test', function(){
//     return 1;
// });
// Route::post('/register', [AuthController::class, 'register']);
// Route::get('/check', function () {
//     $id = 3;
//     $roles = DB::select("CALL user_childs(" . $id . ")");
//     dd($roles);
// });



//gather user data :temporary for account making
Route::post('/userdata', function (Request $request) {
    $existing = UserData::where('email', $request->email)->first();
    if (!$existing) {
        $newUser = new UserData();
        $newUser['name'] = $request->name;
        $newUser['email'] = $request->email;
        $newUser['password'] = $request->password;
        $newUser['joining_date'] = $request->joining_date;
        $newUser['designation'] = $request->designation;
        $newUser['salary'] = $request->salary;
        $newUser['contact_no'] = $request->contact_no;
        if ($newUser->save()) {
            return response()->json(["payload" => $newUser], 201);
        } else {
            return response()->json(["message" => "Error in submitting,Retry!"], 400);
        }
    } else {
        return response()->json(["message" => "Already Exist!"], 400);
    }
});
Route::get('/userdata', function () {
    $alluser = UserData::all();
    if ($alluser) {
        return response()->json(["payload" => $alluser], 200);
    } else {
        return response()->json("Bad Request!", 400);
    }
});




//login route
Route::post('/login', [AuthController::class, 'login']);

//doc download
Route::get('/doc/download/{id}', [DocController::class, 'download_file']);
Route::middleware('auth:api')->group(function () {
    Route::get('/free', [BasicController::class, 'free_resources']);
    Route::post("/resource/lastTask", [BasicController::class, "resource_last_task"]);
    Route::get('/prodepartgress', [BasicController::class, 'project_progresses']);
    Route::post('/data/depart', [BasicController::class, 'department_data']);
    Route::get('/projectresource/{id}', [BasicController::class, 'project_resources']);
    Route::post("/filter/task", [BasicController::class, "resource_task"]);
    // Route::get('/hello',function(){
    //     return response()->json('hello');
    // });
    Route::get('/logout', [AuthController::class, 'logout']);

    Route::resource('/department', DepartmentController::class);
    // Route::get('/roles', [RoleController::class, 'get_roles']);
    Route::post("/changepassword", [UserController::class, 'change_password']);
    Route::resource('/role', RoleController::class);
    Route::resource('/permission', PermissionController::class);
    Route::get('/my/permission/{id}', [PermissionController::class, 'role_permissions']);
    Route::post('/assign/permission', [PermissionController::class, 'assign_permission']);
    Route::post('/remove/permission', [PermissionController::class, 'remove_permission']);
    // Route::delete('/logout', [AuthController::class, 'logout']);
    Route::resource('user', UserController::class);
    Route::resource('project', ProjectController::class);
    Route::get('project/init/resource', [ProjectController::class, 'initial_resource']);
    Route::post('project/resource/{id}', [ProjectController::class, 'assign_resources']);
    Route::get('project/cost/{id}', [ProjectController::class, 'cost']);
    Route::resource('docs', DocController::class);
    // Route::get('/doc/download/{id}', [DocController::class, 'download_file']);
    Route::get('/task/{id}', [TaskController::class, 'my_tasks']);

    Route::resource('tasks', TaskController::class);


    Route::post('/tasks/status/{id}', [TaskController::class, 'change_status']);
    Route::post('/tasks/my/status', [TaskController::class, 'my_task_change_status']);
    Route::put('/tasks/my/{id}', [TaskController::class, 'update_mytask']);


    Route::post('tasks/action/{id}', [TaskController::class, 'task_action']);
    Route::post('tasks/resource/{id}', [TaskController::class, 'assign_resources']);
    Route::resource('resources', ResourceController::class);
    Route::get('employee', [EmployeeController::class, 'index']);
    Route::post('employee', [EmployeeController::class, 'store']);
    Route::put('employee/{id}', [EmployeeController::class, 'update']);
    Route::post('issue/status/{id}', [IssueController::class, 'change_status']);
    Route::put('issue/{id}', [IssueController::class, 'update']);
    Route::delete('issue/{id}', [IssueController::class, 'destroy']);
    Route::get('/variables', [BasicController::class, 'get_variables']);
    Route::get('/db/detail', [BasicController::class, 'details']);
    Route::get('/variables/detail/{id}', [BasicController::class, 'get_variable_values']);
    Route::get('/variables/status/{id}', [BasicController::class, 'get_status']);
    Route::get('/stats/project', [BasicController::class, 'get_project_stats']);
    Route::get('/stats/task', [BasicController::class, 'get_task_stats']);
    Route::post("/tag", [BasicController::class, 'add_tag']);
    Route::post("/tag/assign", [BasicController::class, 'assign_status_to_tag']);
});
