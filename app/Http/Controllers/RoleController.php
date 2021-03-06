<?php

namespace App\Http\Controllers;

// use App\Models\Role;

use App\Models\Roles;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Http\Traits\ResponseTrait;
use App\Http\Traits\ReusableTrait;
// use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class RoleController extends Controller
{
    use ResponseTrait, ReusableTrait;
    public function __construct()
    {
        $this->middleware(['auth']);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // return $this->success_response('hello',200);
        //if super  admin
        if (auth()->user()->role_id == 1 && auth()->user()->can('retrieve role')) {
         
            // if($request->query("all") == "true"){
            //     $roles = Roles::with('parent:id,name')->where('created_by', auth()->user()->id)->where('deleted_at', NULL)->get();
            // }else{
            //     $roles = Roles::with('parent:id,name')->where('created_by', auth()->user()->id)->where('deleted_at', NULL)->paginate(12);
            // }
            $roles = Roles::with('parent:id,name')->where('created_by', auth()->user()->id)->where('deleted_at', NULL)->get();
         
            if ($roles) {
                return $this->success_response($roles->toArray(), 200);
            } else {
                return $this->error_response("Not found", 404);
            }
        } else if (auth()->user()->can('retrieve role')) {

            //retrieve child roles
            $childRoles = collect($this->get_child_roles(auth()->user()));
            $childRoles->push(auth()->user()->role_id);
            // if($request->query("all") == "true"){
            //     $roles = Roles::with('parent:id,name')->whereIn('parent', $childRoles)->where('deleted_at', NULL)->orWhere('created_by',auth()->user()->id)->get();
            //     $roles[] = auth()->user()->role;
            //     // $roles[] = Roles::with('parent:id,name')->where('id', auth()->user()->role_id)->where('deleted_at', NULL)->first();
            // }else{
            //     $roles = Roles::with('parent:id,name')->whereIn('parent', $childRoles)->where('deleted_at', NULL)->paginate(12);
            // }
            $roles = Roles::with('parent:id,name')->whereIn('parent', $childRoles)->where('deleted_at', NULL)->orWhere('created_by',auth()->user()->id)->get();
            $roles[] = auth()->user()->role;
            // $roles = Role::all();
            if ($roles) {
                return $this->success_response($roles, 200);
            } else {
                return $this->error_response("Not found", 404);
            }
        } else {
            return $this->error_response("Forbidden!", 403);
        }
    }


    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        if (auth()->user()->can('create role')) {
            $this->validate($request, [
                'name' => 'required'
            ]);
            $exist = Role::where('name',$request->name)->where('deleted_at',null)->first();
            if($exist){
                return $this->error_response("Already Exist",400);
            }
            $role = new Role();
            $role->fill($request->all());
            $role['created_by'] = auth()->user()->id;
            if ($role->save()) {
                // if(auth()->user()->role_id == 1){

                // }
                $newRole = Roles::with('parent:id,name')->where('id',$role->id)->where('deleted_at', NULL)->first();
                return $this->success_response($newRole, 201);
            } else {
                return $this->error_response("Error in saving the role", 400);
            }
        } else {
            return $this->error_response("Forbidden!", 403);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

        $role = Role::find(auth()->user()->role_id);
        if ($role) {
            return $this->success_response($role, 200);
        } else {
            return $this->error_response("Not found", 404);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        if (auth()->user()->can('edit role')) {
            $exist = Role::find($id);
            if (!$exist) {
                return $this->error_response("Not found", 404);
            }

            $exist = $exist->fill($request->all());
            $exist['updated_by'] = auth()->user()->id;
            if ($exist->save()) {
                $role = Roles::with('parent:id,name')->where('id',$exist->id)->first();
                return $this->success_response($role, 200);
            } else {
                return $this->error_response("Error in saving ,bad request", 400);
            }   
        } else {
            return $this->error_response("Forbidden!", 403);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {

        if (auth()->user()->can('delete role')) {
            $exist = Roles::find($id);
            if (!$exist) {
                return $this->error_response("Not found", 404);
            }
            if ($exist->delete()) {
                return $this->success_response($exist, 200);
            } else {
                return $this->error_response("Error in deleting", 400);
            }
        } else {
            return $this->error_response("Forbidden!", 403);
        }
    }
}
