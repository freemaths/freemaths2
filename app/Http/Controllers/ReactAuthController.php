<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Log;
use Auth;
use App\Help;
//use Illuminate\Foundation\Auth\ThrottlesLogins;
//use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;

class ReactAuthController extends Controller
{
    //
	//use AuthenticatesAndRegistersUsers, ThrottlesLogins;
    
	public function __construct()
	{
		//$this->middleware('guest', ['except' => 'logout']);
	}
	
	public function ajax_help(Request $request,$topic,$user_id=1)
	{
		//$this->log_event($request,ucwords(str_replace('_',' ',$topic)));
		if ($request->user() != null) $request->user()->logs()->create([
				'event'=>'Help',
				'paper'=> $request->has('paper')?$request->paper:'',
				'question'=>$request->has('question')?$request->question:'',
				'answer'=>$topic,
				'comment'=>$request->has('comment')?$request->comment:'',
				'variables'=>$request->has('vars')?json_encode($request->vars):''
		]);
		Log::info('ajax_help('.$topic.')');
		if ($request->ajax())
		{
			$help = Help::where([['title',$topic],['next_id',0],['user_id',$user_id]])->first();
			if (!$help) $help=Help::where([['title',ucwords(str_replace('_',' ',$topic))],['next_id',0],['user_id',$user_id]])->first();
			if ($help) return response()->json($help);
			else return response()->json(['text'=>"Help Not Found",'title'=>$topic]);
		}
	}
	
	private function ret(Request $request)
	{
		$tutor = $admin = $uid = -1;
		if (Auth::check())
		{
			$name = Auth::user()->name;
			$uid = Auth::user()->id;
			if ($request->user()->students()) $tutor = true;
			$admin = (Auth::user()->id == 1);
		}
		else {
			$name='';
		}
		Log::debug("csrf:",['uid'=>$uid,'name'=>$name,'csrf'=>csrf_token(),'isTutor'=>$tutor,'isAdmin'=>$admin]);
		return response()->json(['uid'=>$uid,'name'=>$name,'csrf'=>csrf_token(),'isTutor'=>$tutor,'isAdmin'=>$admin]);
	}
	
	public function csrf(Request $request)
	{
		if ($request->ajax())
		{
			return $this->ret($request);
		}
	}
	
	
	public function login(Request $request)
	{
		if ($request->ajax()) {
			$credentials = $request->only('email', 'password');
			if ($request->has('remember')) Auth::attempt($credentials, $request->remember);
			else Auth::attempt($credentials);
		}
		return $this->ret($request);
	}
	
	
	public function logout(Request $request)
	{
		if ($request->ajax())
		{
			Auth::logout();
			return $this->ret($request);
		}
	}
}
