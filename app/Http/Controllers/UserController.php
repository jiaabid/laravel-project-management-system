<?php

namespace App\Http\Controllers;


use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Http\Traits\ReusableTrait;
use App\Http\Traits\ResponseTrait;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    use ReusableTrait, ResponseTrait;

    public function __construct()
    {
        $this->middleware(['auth']);
    }

    // private $reusable = ReusableQueries::class;
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        //if user is super admin then it will get all the user created by hime
        if (auth()->user()->can('retrieve user') && auth()->user()->id == 1) {
            // return auth()->user()->role_id;

            // $users = User::where('created_by', auth()->user()->id)->with('role:id,name')->with('department:id,name')->get();
            // if ($request->query("all") == "true") {
            //     $users = User::where('created_by', auth()->user()->id)->with('role:id,name')->with('department:id,name')->get();
            // } else {
            //     $users = User::where('created_by', auth()->user()->id)->with('role:id,name')->with('department:id,name')->paginate(12);
            // }

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
            // return $roles;
            // return $childUsers;
            // if ($request->query("all") == "true") {
            //     $users = auth()->user()->admin ? User::whereIn('role_id', $roles)->with('role:id,name')->with('department:id,name')->with('detail')->get() :
            //         User::whereIn('role_id', $roles)->where('dept_id', auth()->user()->dept_id)->with('role:id,name')->with('department:id,name')->with('detail')->get();
            // } else {
            //     // return auth()->user()->role_id;
            //     $users = auth()->user()->admin ? User::whereIn('role_id', $roles)->with('role:id,name')->with('department:id,name')->with('detail')->paginate(12) :
            //         User::whereIn('role_id', $roles)->where('dept_id', auth()->user()->dept_id)->with('role:id,name')->with('department:id,name')->with('detail')->paginate(12);
            //     // $users = User::whereIn('role_id', $roles)->whereIn('id', $childUsers)->with('role:id,name')->with('department:id,name')->with('detail')->paginate(12);
            // }
            $users = auth()->user()->admin ? User::whereIn('role_id', $roles)->with('role:id,name')->with('department:id,name')->with('detail')->get() :
                User::whereIn('role_id', $roles)->where('dept_id', auth()->user()->dept_id)->with('role:id,name')->with('department:id,name')->with('detail')->get();
            // $users = User::whereIn('role_id', $roles)->with('role:id,name')->with('department:id,name')->with('detail')->get();
            if ($users) {
                return $this->success_response($users, 200);
            } else {
                return $this->error_response("No user exist!", 404);
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

        if (auth()->user()->can('create user')) {
            $this->validate($request, [
                'name' => "required|min:3",
                'email' => "required|email",
                'password' => "required|min:5",
                'role_id' => 'required',
                'dept_id' => 'required_if:admin,==,false'
            ]);
          $exist = User::where('deleted_at',null)->where('email',$request->email)->first();
          if($exist){
              return $this->error_response("Email already occupied!",400);
          }
            DB::beginTransaction();
            $user = new User();
            $user = $user->fill($request->all());
            $user['password'] = Hash::make($request->password);
            $user->assignRole($request->role_id);
            $user['created_by'] = auth()->user()->id;
            $user['admin'] = !$request->admin ? false : true;
            $user->save();
            DB::commit();
            $user->role;
            $user->department;
            $data = [
                'to' => 'wajiha@outcastsolutions.us',
                'msg' => 'Account is successfully created!',
                'email' => $user->email,
                'password' => $request->password
            ];

            //send email as the account is created
            Mail::send([], [], function ($message) use ($data) {
                $message->to($data['email'])
                    ->subject('Project Management System Account')
                    ->from('test@outcastsolutions.us')
                    ->setBody('<html><h6>Your account on Project Management System has been setup successfully!</h6><p>Redirect to the link below to login.</p><p><a href="https://management.outcastsolutions.us/">project management system</a></p><br><p>Credentials:<br><b>Email:</b> ' .
                        $data['email'] .
                        '</p><p><b>Password:</b>' . $data['password'] . '</p></html>', 'text/html');
            });
            // $tsuccessen = $user->createTsuccessen('pmsTsuccessen')->accessTsuccessen;
            return $this->success_response($user, 201);
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

        if (auth()->user()->can('retrieve user')) {
            $user = User::find($id);
            $user->detail;
            $user->role;
            $user->department;

            if ($user) {
                return $this->success_response($user, 200);
            }
            return $this->error_response("Not Found", 404);
        } else {
            return $this->error_response("Forbidden!", 403);
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

        if (auth()->user()->can('edit user')) {
            $user = User::find($id);
            if (!$user) {
                return $this->error_response("Not found", 404);
            }
            $user->roles()->detach();
            // $user->removeRole($user->role_id);
            $user->fill($request->only('name', 'email', 'phone_number', 'role_id', 'dept_id'));
            $user->assignRole($request->role_id);
            $saved = $user->save();
            if ($saved) {
                return $this->success_response($user, 200);
            } else {
                return $this->error_response("Error in updating", 400);
            }
        } else {
            return $this->error_response("Forbidden!", 403);
        }
    }
    /**
     * change the password in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */

    public function change_password(Request $request)
    {

        if (auth()->user()->can('retrieve user')) {

            $this->validate($request, [
                'oldpassword' => "required",
                'password' => "required|same:c_password",
                'c_password' => "required"
            ]);
            if (Hash::check($request->oldpassword, auth()->user()->password)) {
                $existing = User::where('id', auth()->user()->id)->first();
                $existing['password'] = Hash::make($request->password);
                $existing->save();
                // $user =  User::where('id', auth()->user()->id)
                //     ->update(['password', Hash::make($request->password)]);
                return $this->success_response($existing, 200);
            } else {
                return $this->error_response("Wrong password!", 400);
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

        if (auth()->user()->can('delete user')) {
            $user = User::find($id);
            if (!$user) {
                return $this->error_response("Not found", 404);
            }
            if ($user->delete()) {
                return $this->success_response($user, 200);
            } else {
                return $this->error_response("Error in deleting", 400);
            }
        } else {
            return $this->error_response("Forbidden!", 403);
        }
    }
}
