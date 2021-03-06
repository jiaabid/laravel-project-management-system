<?php

namespace App\Http\Controllers;

use App\Models\DbVariablesDetail;
use Exception;
use Illuminate\Http\Request;
use App\Http\Traits\ResponseTrait;
use App\Models\DbVariables;
use App\Models\TagStatus;
use App\Models\Project;
use App\Models\User;
use App\Http\Traits\ReusableTrait;
use App\Models\Department;
use App\Models\HResourcesTask;
use App\Models\Task;
use Illuminate\Support\Facades\DB;

class BasicController extends Controller
{
    use ReusableTrait, ResponseTrait;


    /**
     * get values of specified variable type from database
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int $id
     * @return \Illuminate\Http\Response
     */
    public function get_variable_values(Request $request, $id)
    {

        $values =  DbVariablesDetail::where('variable_id', $id)->get();
        if (!$values) {
            return $this->error_response("Not found", 404);
        }
        return $this->success_response($values, 200);
    }

    //get the database variables    
    /**
     * get variable types from database e.g:(task_status,user_type)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function get_variables(Request $request)
    {

        $variables = DbVariables::all();
        if (!$variables) {
            return $this->error_response("Not found", 404);
        }
        return $this->success_response($variables, 200);
    }

    //get the database variables detail   
    /**
     * get variable types from database e.g:(task_status,user_type)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function details()
    {

        $variables = DbVariablesDetail::all();

        if (!$variables) {
            return $this->error_response("Not found", 404);
        }
        return $this->success_response($variables, 200);
    }

    /**
     * get statuses specified to the tag
     *
     * @param  int $id (tagId)
     *@return \Illuminate\Http\Response
     */
    public function get_status($id)
    {

        $statuses = TagStatus::where('tag_id', $id)->with('variable_detail:value,id')
            ->get(["id", "status_id"]);
        if (!$statuses) {
            return $this->error_response("Not found", 404);
        }
        return $this->success_response($statuses, 200);
    }

    public function get_task_stats()
    {
        if (auth()->user()->admin) {
            // $tasks = Task::where('deleted_at', null)->where('type', 18)
            //     // ->select(DB::raw('status,CAST(count(*) AS UNSIGNED )as count'))
            //     ->selectRaw('status,CAST(count(*) as SIGNED) as count')
            //     ->groupBy('status')
            //     ->get();
            $tasks = Task::where('deleted_at', null)->where('type', 18)
                // ->select(DB::raw('status,CAST(count(*) AS UNSIGNED )as count'))

                ->get();
            $tasks =  $tasks->groupBy('status')
                ->map
                ->count();
            $taskStats = collect([]);
            $keys = $tasks->keys();
            foreach ($keys as $key) {
                $taskStats->push([
                    "status" => $key,
                    "count" => $tasks[$key]
                ]);
            };
            // $tasks = Task::where('deleted_at', null)->where('type', 18)
            //     ->where('status',11) 
            // ->get();
            return $this->success_response($taskStats, 200);
        } else {
            //assign by me tasks
            $completed = 0;
            $issue = 0;
            $pending = 0;
            $inReivew = 0;
            $inProgress = 0;
            // $tasks = HResourcesTask::where('resource_id', auth()->user()->id)->where('deleted_at', null)->select(DB::raw('status,count(*) as count'))->groupBy('status')->get();
            $task = auth()->user()->assigned_task()->get();
            foreach ($task as $item) {
                if ($item->status == 15) {
                    $completed++;
                }
                if ($item->status == 14) {
                    $inReivew++;
                }
                if ($item->status == 13) {
                    $issue++;
                }
                if ($item->status == 12) {
                    $inProgress++;
                }
                if ($item->status == 11) {
                    $pending++;
                }
            }
            $tasks = [
                [
                    "status" => 15,
                    "count" => $completed
                ],
                [
                    "status" => 14,
                    "count" => $inReivew
                ], [
                    "status" => 13,
                    "count" => $issue
                ], [
                    "status" => 12,
                    "count" => $inProgress
                ], [
                    "status" => 11,
                    "count" => $pending
                ],
            ];


            return $this->success_response($tasks, 200);
        }
    }

    public function get_project_stats()
    {
        // dd('fsfsf');
        // return auth()->user()->can('create project');
        if ((auth()->user()->can('retrieve project') && auth()->user()->admin)) {

            //retrieve child roles
            $roles = $this->get_child_roles(auth()->user());
            $roles->push(auth()->user()->role_id);

            //completed projects
            $project  = Project::with('user')->whereHas('user', function ($query) use ($roles) {
                return $query->whereIn('role_id', $roles);
            })->select(DB::raw('status,count(*) as count'))
                ->groupBy('status')->where('deleted_at', null)->get();



            //late
            $lateProjects  = Project::with('user')->whereHas('user', function ($query) use ($roles) {
                return $query->whereIn('role_id', $roles);
            })->where('late', true)->where('deleted_at', null)->count();
            return $this->success_response(["status" => $project, "late" => $lateProjects], 200);
        }
        //if the user has created a project
        else if (auth()->user()->can('retrieve project') && auth()->user()->can('create project')) {
            //retrieve child roles
            $roles = $this->get_child_roles(auth()->user());
            $roles->push(auth()->user()->role_id);

            //completed projects
            $project  = Project::with('user')->whereHas('user', function ($query) use ($roles) {
                return $query->whereIn('role_id', $roles);
            })->where('dept_id', auth()->user()->dept_id)->where('deleted_at', null)->select(DB::raw('status,count(*) as count'))
                ->groupBy('status')->where('deleted_at', null)->get();

            //late
            $lateProjects  = Project::with('user')->whereHas('user', function ($query) use ($roles) {
                return $query->whereIn('role_id', $roles);
            })->where('dept_id', auth()->user()->dept_id)->where('late', true)->where('deleted_at', null)->count();
            return $this->success_response(["status" => $project, "late" => $lateProjects], 200);
        } else {


            $completedProjects = 0;
            $pendingProjects = 0;
            $lateProjects = 0;

            $lateProjects = auth()->user()->projects->where('deleted_at', null)->where('late', 1)->count();
            foreach (auth()->user()->projects->where('deleted_at', null) as $project) {
                if ($project->status == 6) {
                    $completedProjects++;
                }
                // else if ($project->late == true) {
                //     $lateProjects++;
                // }

                else {
                    $pendingProjects++;
                }
            };
            $project = [
                [
                    "status" => 5,
                    "count" => $pendingProjects
                ],
                [
                    "status" => 6,
                    "count" => $completedProjects
                ]
            ];
            return $this->success_response(["status" => $project, "late" => $lateProjects], 200);
        }
    }
    public function get_user_stats()
    {
        //if user is super admin then it will get all the user created by hime
        if (auth()->user()->can('retrieve user') && auth()->user()->id == 1) {
            $users = User::where('created_by', auth()->user()->id)->with('role:id,name')->with('department:id,name')->get();

            if ($users) {
                return $this->success_response($users, 200);
            } else {
                return $this->error_response("No user exist!", 404);
            }
        }

        //get the child users (role hierarchy)
        else if (auth()->user()->can('retrieve user')) {

            //retrieve child roles  
            $roles = collect($this->get_child_roles(auth()->user()));
            $roles->push(auth()->user()->role_id);
            $childUsers = $this->get_child_users(auth()->user());

            $users = auth()->user()->admin ? User::whereIn('role_id', $roles)->with('role:id,name')->with('department:id,name')->with('detail')->get() :
                User::whereIn('role_id', $roles)->where('dept_id', auth()->user()->dept_id)->with('role:id,name')->with('department:id,name')->with('detail')->get();
            if ($users) {
                return $this->success_response($users, 200);
            } else {
                return $this->error_response("No user exist!", 404);
            }
        } else {
            return $this->error_response("Forbidden!", 403);
        }
    }

    public function free_resources()
    {
        $freeResources = [];
        // $resources = HResourcesTask::where('deleted_at',null)->select('resource_id')->distinct()->get()->pluck('resource_id');
        $roles = collect($this->get_child_roles(auth()->user()));
        $roles->push(auth()->user()->role_id);
        $users = auth()->user()->admin ? User::whereIn('role_id', $roles)->get()->pluck('id') : User::whereIn('role_id', $roles)->where('dept_id', auth()->user()->dept_id)->get()->pluck('id');
        // $users = User::whereIn('role_id', $roles)->get()->pluck('id');

        foreach ($users as $resource) {
            $groupdata = HResourcesTask::where('deleted_at', null)->where('resource_id', $resource)->select(DB::raw('status,count(status) as count'))->groupBy('status')->get();
            //    $tt[$resource]= $data;
            //    return $tt;
            if (count($groupdata) > 0) {
                foreach ($groupdata as $data) {
                    if ($data->status !== 15 && $data->count > 0) {
                        break;
                    } else {
                        $freeResources[] = $resource;
                    }
                }
            } else {
                $freeResources[] = $resource;
            }
        };
        // foreach($tt as $t){
        //    return $t;

        // };
        return $this->success_response(User::whereIn('id', $freeResources)->get(['id', 'name']), 200);
    }

    public function project_progresses()
    {
        $projects = Project::where('deleted_at', null)->with('tasks')->get();
        $data = collect([]);
        foreach ($projects as $project) {
            $total = count($project->tasks);
            $completed = $project->tasks->where('deleted_at', null)->where('status', 15)->count();
            $data->push(["id" => $project->id, "name" => $project->name, "total" => $total, "completed" => $completed]);
        };
        return $this->success_response($data, 200);
    }
    public function resource_last_task(Request $request)
    {
        $lastTask = HResourcesTask::where('deleted_at', null)->where("resource_id", $request->resource_id)->max('end_date');
        return $this->success_response($lastTask, 200);
    }
    public function resource_task(Request $request)
    {
        $tasks = [];
        if ($request->resource_id !== null && $request->project_id !== null ) {
            //with resource id
            $tasks = $request->date !== null ? Task::with('team')->whereHas('team', function ($query) use ($request) {
                return $query->where('resource_id', $request->resource_id);
            })->where('project_id', $request->project_id)->where(function ($query) use ($request) {
                return $query->whereBetween('start_date', [$request->date[0], $request->date[1]])
                    ->orWhereBetween('end_date', [$request->date[0], $request->date[1]]);
            })->get() :
                Task::with('team')->whereHas('team', function ($query) use ($request) {
                    return $query->where('resource_id', $request->resource_id);
                })->where('project_id', $request->project_id)->get();
           
        }
        else if ($request->resource_id !== null ) {
            //with resource id
            $tasks = $request->date !== null ? Task::with('team')->whereHas('team', function ($query) use ($request) {
                return $query->where('resource_id', $request->resource_id);
            })->where(function ($query) use ($request) {
                return $query->whereBetween('start_date', [$request->date[0], $request->date[1]])
                    ->orWhereBetween('end_date', [$request->date[0], $request->date[1]]);
            })->get() :
                Task::with('team')->whereHas('team', function ($query) use ($request) {
                    return $query->where('resource_id', $request->resource_id);
                })->get();
           
        }
         else if ($request->project_id !== null) {
            $tasks =$request->date !== null ? Task::with('team')->where('project_id', $request->project_id)
                ->where(function ($query) use ($request) {
                    return $query->whereBetween('start_date', [$request->date[0], $request->date[1]])
                        ->orWhereBetween('end_date', [$request->date[0], $request->date[1]]);
                })->get():
                Task::with('team')->where('project_id', $request->project_id)->get();

            // whereBetween('start_date', [$request->date[0], $request->date[1]])->get();
        } else {
            if(!auth()->user()->dept_id){
                $users = Department::where('id', $request->dept_id)->with('user')->first();
                $userIDs = $users->user->pluck('id');
            }else{
                $roles = collect($this->get_child_roles(auth()->user()));
                // $roles->push(auth()->user()->role_id);
                $userIDs =  User::whereIn('role_id', $roles)->get()->pluck('id') ;
            }
            
            // return $userIDs;
            $tasks = $request->date !== null ?Task::with('team')->whereHas('team', function ($query) use ($userIDs) {
                return $query->whereIn('resource_id', $userIDs);
            })->where(function ($query) use ($request) {
                return $query->whereBetween('start_date', [$request->date[0], $request->date[1]])
                    ->orWhereBetween('end_date', [$request->date[0], $request->date[1]]);
            })->get():
            Task::with('team')->whereHas('team', function ($query) use ($userIDs) {
                return $query->whereIn('resource_id', $userIDs);
            })->get();

            // ->whereBetween('start_date', [$request->date[0], $request->date[1]])
            //     ->get();
        }

        return $this->success_response($tasks, 200);
    }
    public function department_data(Request $request)
    {
        if ($request->mode == 'user') {
            $departUsers = Department::where('id', $request->dept_id)->with('user')->first();
            return $this->success_response($departUsers->user, 200);
        }
    }
    public function project_resources($id)
    {
        $project = Project::where('id', $id)->where('deleted_at', null)->with('human_resource')
            ->first();
        return $this->success_response($project->human_resource, 200);
    }

    public function add_tag(Request $request)
    {
        $exist = DbVariablesDetail::where('value', $request->name)->first();

        if (!$exist) {
            $this->validate($request, [
                'name' => 'required'
            ]);
            $newVariable = new DbVariablesDetail();
            $newVariable['value'] = $request->name;
            $newVariable['variable_id'] = $request->vid;
            $saved = $newVariable->save();
            // dd($newVariable);
            if ($saved) {
                return $this->success_response($newVariable, 201);
            } else {
                return $this->error_response("Error in adding tag!", 400);
            }
        } else {
            return $this->error_response("Already Exist", 400);
        }
    }

    public function assign_status_to_tag(Request $request)
    {
        try {
            foreach ($request->statuses as $status) {
                $oldStatus = TagStatus::where('tag_id', $request->tag)->where('status_id', $status)->first();
                if (!$oldStatus) {
                    $tagStatus = new  TagStatus();
                    $tagStatus['status_id'] = $status;
                    $tagStatus['tag_id'] = $request->tag;
                    $tagStatus->save();
                }
            };
            return $this->success_response("Assigned!", 200);
        } catch (Exception $e) {
            return $this->error_response($e->getMessage(), 500);
        }
    }
}
