<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Log as Log;

class AuthController extends Controller
{
    use AuthorizesRequests;
    public function indexCustomers(){
        $this->authorize('browseUsers',User::class);
        $customers=User::where('is_employee',0)->get();
        return UserResource::collection($customers);
    }
    public function indexEmployees(){
        $this->authorize('browseUsers', User::class);
        $employees = User::where('is_employee', 1)->get();
        return UserResource::collection($employees);
    }
    public function register(Request $request)
    {
        $atts = $request->validate([
            'email' => 'required|string|email|max:255|unique:users,email',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'address' => 'nullable|string|max:500',
            'password' => 'required|string|min:8|confirmed',
            'name' => 'required|string|max:255|unique:users,name'
            ]);
        Log::info('User Data Before Insert:', ['userData' =>$atts]);
        $user = User::create([
            'name' => $atts['name'],
            'email' => $atts['email'],
            'phone'=>$atts['phone'],
            'address'=>$atts['address'] ?? null,
            //here i am using the builr in hash function to hash the password
            'password' => Hash::make($atts['password'])
        ]);
        $user->sendEmailVerificationNotification();
        return response()->json([
            'message' => 'Registration successful. Please check your email to verify your account.'
        ]);
        // to be done letter
        // $user_name = $atts['name'];
        // $user_email = $atts['email'];
        // Mail::to($atts['email'])->send(new WelcomMail($user_name, $user_email));
        // return response()->json([
        //     'message' => 'user instance has been created successfully',
        //     'user' => UserResource::make($user),
        // ]);
    }
    public function seed(Request $request){
        $users=User::all();
        if($users){
            foreach($users as $user){
                $user->delete();
            }
        }
        $categories=Category::all();
        if($categories){
            foreach($categories as $category){
                $category->delete();
            }
        }
        $roles=Role::all();
        if($roles){
            foreach($roles as $role){
                $role->delete();
            }
        }
        $user=User::create([
            'name' => 'admin',
            'email' => 'admin@dentalhub.com',
            'is_employee' => true,
            'password' => env('ADM_PAS'), // password
        ]);
        $role=Role::create([
            'type' => 'admin',
        ]);
        Role::create([
            'type' => 'sales',
        ]);
        Role::create([
            'type' => 'accounting',
        ]);
        Role::create([
            'type' => 'inventory',
        ]);
        Role::create([
            'type' => 'reporting',
        ]);
        DB::table('role_user')->insert([
            'role_id' => $role->id,
            'user_id' => $user->id,
        ]);
        Category::create([
            'name' => 'other',
            's_name' => 'other',
            'enabled' => true,
        ]);
}
    public function create(Request $request){
        $atts=$request->validate([
            'name'=>'string|required',
            'email'=>'string|email|required|unique:users,email',
            'phone' => 'nullable|string|max:20|unique:users,phone',
            'address' => 'nullable|string|max:500',
            'password'=>'string|required|confirmed',
            'is_employee'=>'boolean|required_with:roles',
            'roles'=>'array|required_with:is_employee',
            'roles.*'=>'string|exists:roles,type'
        ]);
        Log::error($request->user()->roles()->get());
        // $this->authorize('createUser',User::class);
        Log::info('Employee Data Before Insert:', ['employeeData' => $atts]);
        try {
        DB::transaction(function () use ($atts, &$user) {
                    $user = User::create([
                        'name' => $atts['name'],
                        'email' => $atts['email'],
                        'phone' =>$atts['phone'],
                        'address' => $atts['address'] ?? null,
                        'password' => Hash::make($atts['password']),
                        'is_employee' => $atts['is_employee'] ?? false,
                        'email_verified_at' => now()
                    ]);
                    if ($user->is_employee && isset($atts['roles'])) {
                        $user->assignRoles($atts['roles']);
                    }
                });
        } catch (\Exception $e) {
            Log::error('Error creating employee user: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create employee user'], 500);
        }
        return response()->json([
            'message' => 'Employee user created successfully',
            'user' => UserResource::make($user)
        ], 201);


    }
    public function login(Request $request){
        $atts=$request->validate([
            'email'=>'required|email|exists:users,email',
            'password'=>'required'
        ]);
        $user = User::where('email', $atts['email'])->first();
        if (!$user || !Hash::check($atts['password'], $user->password)) {
            return response()->json(['message' => 'wrong cardinatials'], 400);
        }
        if($user->is_employee==1 || $user->roles()->exists()){
            return response()->json(['message' => 'Access denied. Employees must use the employee api to log in.'], 403);
        }
        $token=$user->createToken($user->name);
        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken
        ]);
    }
    public function employeeLogin(Request $request){
        $atts=$request->validate([
            'email'=>'required|email|exists:users,email',
            'password'=>'required'
        ]);
        $user = User::where('email', $atts['email'])->first();
        if (!$user || !Hash::check($atts['password'], $user->password)) {
            return response()->json(['message' => 'wrong cardinatials'], 400);
        }
        if($user->is_employee!=1 || !$user->roles()->exists()){
            return response()->json(['message' => 'Access denied. Only employees can use the employee api to log in.'], 403);
        }
        $token=$user->createToken($user->name);
        return response()->json([
            'user' => $user,
            'token' => $token->plainTextToken
        ]);
    } 
    public function logout(Request $request){
        //to ask louy
        //$request->user()->tokens()->delete();
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message'=>'logged out successfully']);
    }
    public function verifyEmail($id,$hash){
        $user = User::findOrFail($id);
        // Validate hash
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return response()->json(['message' => 'Invalid verification link'], 403);
        }

        // Already verified?
        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified']);
        }

        // Mark as verified
        $user->markEmailAsVerified();
        return view('success');
    }
    public function resendEmailVerification(Request $request){
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified'], 400);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email resent']);
    }
    public function me(Request $request){
        return UserResource::make($request->user());
    }
    public function deleteUser(Request $request, $id){
        $this->authorize('deleteUser', User::class);
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        if($request->user()->id == $user->id){
            return response()->json(['message' => 'You cannot delete your own account'], 403);
        }
        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }
    public function updateRate(Request $request,$id){
        $this->authorize('browseUsers',User::class);
        $atts=$request->validate([
            'rate'=>'required|numeric|max:5|min:0'
        ]);
        $user=User::find($id);
        if(!$user){
            return response()->json(['message'=>'user not found , what are you trying to do dude'],404);
        }
        $user->rate=$atts['rate'];
        $user->save();
        return response()->json(['message'=>'rate has been updated succeffuly'],200);
    }
    public function block(Request $request,$id){
        $this->authorize('browseUsers',User::class);
        $user=User::find($id);
        if(!$user){
            return response()->json(['message'=>'user not found , what are you trying to do dude'],404);
        }
        $user->status=false;
        $user->save();
        return response()->json(['message'=>'user has been blocked succeffuly'],200);
    }
    public function unblock(Request $request,$id){
        $this->authorize('browseUsers',User::class);
        $user=User::find($id);
        if(!$user){
            return response()->json(['message'=>'user not found , what are you trying to do dude'],404);
        }
        $user->status=true;
        $user->save();
        return response()->json(['message'=>'user has been unblocked succeffuly'],200);
    }
    // public function updateUser(Request $request, $id){
    //     $this->authorize('editUser', User::class);
    //     $user = User::find($id);}
    // public function sendResetLinkEmail(Request $request)
    // {
    //     $atts = $request->validate([
    //         'email' => 'required|email|exists:users,email',
    //     ]);

    //     $user = User::where('email', $atts['email'])->first();
    //     if(!$user){
    //         return response()->json(['message' => 'User with this email not found'], 404);
    //     }
    //     $user->sendPasswordResetNotification();
    //     return response()->json([
    //         'message' => 'Password reset link has been sent to your email address.'
    //     ]);
    // }

}
