<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Auth;
use Validator;
use App\Models\User;
use App\Models\Profile;


class AuthController extends Controller
{
    public function _construct(){
        $this->middleware('auth:api',['except'=>['login','register']]);
    }
    public function register(Request $request){
        $validator = Validator::make($request->all(),[
            'name'=>'required',
            'email'=>'required|string|email|unique:users',
            'password'=>'required|string|min:6'
        ]);
        if($validator->fails()){
            return response()->json($validator->errors()->toJson(),400);
        }
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->password = bcrypt($request->password);
        $user->save();

        $profile = new Profile();
        $profile->user_id = $user->id;
        $profile->name = $user->name;
        $profile->save();


        return response()->json([
            'message'=>'User successfully registered',
            'user'=>$user
        ],201);
    }
    public function login(Request $request){
        $validator = Validator::make($request->all(),[ 
            'email'=>'required|email',
            'password'=>'required|string|min:6'
        ]);
        
        if($validator->fails()){
            return response()->json($validator->errors(),422);
        }
        if (!$token=auth()->attempt($validator->validated())) {
            return response()->json(['error'=>'Unauthorized'],401);
        }
        return $this->createNewToken($token);
    }
    public function createNewToken($token){
        return response()->json([
            'access_token'=>$token,
            'token_type'=>'bearer',
            'expires_in'=>auth()->factory()->getTTL()*180,
            'user'=>auth()->user()
        ]);
    }

    public function logout(){
        auth()->logout();
        return response()->json([
            'message'=>'User logged out',
        ]);
    }


    public function edit(Request $request){
        $user = auth()->user();
        $profile = auth()->user()->profile;

        $validator = Validator::make($request->all(),[
            'image'=>'required',
            'cover'=>'required',
            'bio'=>'required|string'
        ]);

        if($validator->fails()){
            return response()->json($validator->errors()->toJson(),400);
        }

        $image = $request->file('image');
        $coverImage = $request->file('cover');
        if ($request->hasFile('image') && $request->hasFile('cover')) {
            $image_new_name = $user->id.'.'.$image->getClientOriginalExtension();
            $image->move(public_path('/upload/profileImage'),$image_new_name);
            $cover_new_name = $user->id.'.'.$coverImage->getClientOriginalExtension();
            $coverImage->move(public_path('/upload/coverImage'),$cover_new_name);
            //updating
            $profile->image = $image_new_name;
            $profile->cover = $cover_new_name;
            $profile->bio = $request->bio;
            $profile->save();
        }else{
            return response()->json('somthing went wrong',400);
        }


        return response()->json([
            'profile'=>$profile,
        ]);
    }

    public function store($profileId)
    {
        return auth()->user()->following()->toggle($profileId);
    }

    public function followersNb($userId){
        $user = User::find($userId);
        $followers = $user->followers->count();
        $followings = $user->followings->count();

        return response()->json([
            'followers'=>$followers,
            'followings'=>$followings
        ]);
    }

    public function followers($userId){
        $user = User::find($userId);
        $followers = $user->followers;
        $profiles=array();
        for ($i=0; $i < count($followers); $i++) { 
            $profile = $followers[$i]->profile;
            $profiles[]=$profile;
        }
        return response()->json([
            'followers'=>$profiles,
        ]);
    }

    public function profiles()
    {
        $profiles = Profile::All();

        return response()->json([
            'profiles'=>$profiles,
        ]);
    }

    
    public function profile($profileId)
    {
        $user_id = $profileId;

        $profile = Profile::where('user_id',$user_id)->first();
        return response()->json([
            'profile'=>$profile,
        ]);
    }

    public function amIfollowing($profileId)
    {   
        $user = auth()->user();
        $followings = $user->followings;
        for ($i=0; $i < count($followings); $i++) {
            if ($followings[$i]->id==$profileId) {
                return response()->json(true);
            }
        }
        return response()->json(false);
    }
}
