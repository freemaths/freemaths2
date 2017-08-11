<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use DB;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password',
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];
    
    public function logs()
    {
    	return $this->hasMany(Log::class);
    }
    
    public function tutors()
    {
    	return $this->hasMany(Tutor::class);
    }
    
    public function students($all = false)
    {
    	if ($all && $this->email == 'ed@darnell.org.uk') return DB::table('users')->pluck('id');
    	return DB::table('tutors')->where('email', $this->email)->pluck('user_id');
    }
    
    public function student($uid)
    {
    	if (DB::table('tutors')->where(['email' => $this->email,'user_id'=>$uid])->first()) return true;
    	else return false;
    }
}
