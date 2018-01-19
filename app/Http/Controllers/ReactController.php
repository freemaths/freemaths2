<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Question;
use App\Test;
use App\Help;
use App\Marking;
use App\PastPaper;
//use App\Stats\Stats;
use App\Log as StatLog;
use App\User;
use Log;
use DB;
use Storage;

class ReactController extends Controller
{
    //
    public function __construct()
	{
		$this->middleware('auth');
	}
	
	public function ajax_stats(Request $request)
	{
		if($request->ajax()){
			if (!$request->last)
			{
				$tests=Test::all();
				$questions=Question::where('next_id',0)->get();
				$qmap=DB::table('question_test')->get();
			}
			if ($request->students && $students=$request->user()->students($request->all))
			{
				Log::debug('ajax_stats:',['students'=>$students]);
				if ($request->last) $log=StatLog::whereIn('user_id',$students)->where('id','>',$request->last)->orderBy('id','asc')->get();
				else
				{
					$log=StatLog::whereIn('user_id',$students)->orderBy('id','asc')->get();
					$users=User::whereIn('id',$students)->select('id','name')->get();
				}
			}
			else
			{
				if ($request->last) $log=StatLog::where('user_id',$request->user()->id)->where('id','>',$request->last)->orderBy('id','asc')->get();
				else {
					$log=StatLog::where('user_id',$request->user()->id)->orderBy('id','asc')->get();
					$users=[['id'=>$request->user()->id,'name'=>$request->user()->name]];
				}
	
			}
			if ($request->last) return response()->json(['log'=>$log]);
			else return response()->json(['log'=>$log,'users'=>$users,'tests'=>$tests,'questions'=>$questions,'qmap'=>$qmap]);
		}
	}
	
	public function ajax_Q(Request $request)
	{
		if($request->ajax() && ($question = $request->question)){
			$question = $request->question;
			Log::debug('ajax_Q',['question'=>$question]);
			if (isset($question['delete']))
			{
				if ($question['delete']>0) Question::find($question['delete'])->delete();
				return response()->json($question);
			}
			else
			{
				if ($question['id'] != 0) $q = Question::find($question['id']);
				else $q = new Question;
				if (!isset($q->previous_id) && isset($question['previous_id'])) $q->previous_id=$question['previous_id'];
				$q->next_id=0;
				unset($question['number']); // stored on test_question
				unset($question['tests']); // stored on test_question
				//unset($question['marks']); // stored on test_question
				$q->json = json_encode($question);
				$q->user()->associate($request->user());
				$q->save(); // json id corrected
				$question['id']=$q->id;
				return response()->json($question); // not sure we should return it
			}
		}
	}
	
	public function ajax_import(Request $request)
	{
		if($request->ajax())
		{
			if ($request->input('get'))
			{
				if (($request->type == 'help' && ($rows = Help::orderBy('id')->get()))
						||($request->type == 'tests' && ($rows = Test::orderBy('id')->get())))
				{
					foreach ($rows as $row)
					{
						if ($row->next_id != 0) continue;
						$filename = $request->type . "/{$row->title}.{$row->id}.json";
						Log::debug('ajax_import',['type'=>$request->type,'title'=>$row->title,'id'=>$row->id]);
						if (!Storage::exists($filename)) Storage::put($filename,json_encode($row));
						$latest[$row->title]=['id'=>$row->id,'updated_at'=>strtotime($row->updated_at)];
					}
				}
				$ret = [];
				$files = Storage::files($request->type);
				foreach ($files as $file)
				{
					$name = explode('.',explode('/',$file)[1])[0];
					$ver = $name.'.'.explode('.',explode('/',$file)[1])[1];
					if (ends_with($file,'.json')) {
						$f= json_decode(Storage::get($file));
						if (isset($f->updated_at)) $timestamp = strtotime($f->updated_at);
						else $timestamp=Storage::lastModified($file);
						if (isset($f->type)) $type = $f->type;
						else $type = $request->type;
						Log::debug($name,['file'=>$timestamp,'db'=>isset($latest[$name]['updated_at'])?$latest[$name]['updated_at']:'absent']);
						if (!isset($latest[$name]) || $timestamp > $latest[$name]['updated_at']) $ret[]=['name'=>$ver,'new'=>!isset($latest[$name]),'type'=>$type];
					}
				}
				return response()->json($ret);
			}
			else
			{
				Log::debug('ajax_import:',['filename'=>$request->data['name']]); //,'t'=>json_encode($t)]);
				$file = $request->type . "/" . $request->data['name'] . '.json';
				if ($json = Storage::get($file))
				{
					if ($t = json_decode($json,true))
					{
						Log::debug('ajax_import:',['oldid'=>isset($t['id'])?$t['id']:0]);
						if ($request->type == 'help')
						{
							$t['id'] = $this->save_help($t,$request->user());
							return response()->json($t);
						}
						else
						{
							if (isset($t['qs'])) $qs = $this->save_QS($t['qs'],$request->user());
							else $qs=null;
							Log::debug('ajax_import:',['qs'=>json_encode($qs)]);
							$id = $this->save_test($t,$qs,$request->user(),false);
							Log::debug('ajax_import:',['newid'=>$id]);
							return response()->json($t);
						};
					}
				}
			}
		}
	}
	
	public function ajax_help_list(Request $request)
	{
		if($request->ajax() && ($rows = Help::where([['next_id',0],['user_id',1]])->get(['id','title'])))
			return response()->json($rows);
	}
	
	public function ajax_tests(Request $request)
	{
		Log::debug('ajax_tests:',['testId'=>$request->testId,'filter'=>$request->filter]);
		if($request->ajax()){
			if ($request->testId != 0)
			{
				$testQs = Test::find($request->testId)->questions()->get();
				Log::debug('ajax_tests:',['testId'=>$request->testId,'response' => $testQs->toJson()]);
				return response()->json($testQs);
			}
			else if ($request->filter == '_pastPapers')
			{
				$tests = Test::where('type', 'past')->get();
				$results = StatLog::where(['user_id'=>$request->user()->id,'event'=>'✓✗'])->orderBy('id', 'desc')->get();
				$tests = ['tests'=>$tests,'marking'=>$results];
			}
			else if ($request->filter)
			{
				$tests = Test::find_keywords($request->filter);
			}
			else $tests = Test::all();
			//Log::debug('ajax_tests: responded');
			return response()->json($tests);
		}
	}
	
	private function get_test($id)
	{
		if ($test = Test::find($id))
		{
			$qs = $test->questions()->orderBy('pivot_number')->get();
			$r = [];
			foreach ($qs as $q)
			{
				$question = json_decode($q->json);
				if (isset($q->pivot)) {
					$question->number = $q->pivot->number;
					unset($question->q);
					if ($q->pivot->marks > 0 || !isset($question->marks)) $question->marks = $q->pivot->marks;
				} else $question->number = 0;
				$question->id = $q->id; // override what is in json
				$r[] = $question;
			}
			$test->qs=$r;
		}
		return $test;
	}
	
	public function ajax_testQs(Request $request)
	{
		if (isset($request->tests)){
			Log::debug('ajax_TestQs:',['tests' => $request->tests]);
			$ts=[];
			foreach($request->tests as $t) {
				$ts[]=$this->get_test($t);
			}
			return response()->json(['tests'=>$ts]);
		}
		else {
			Log::debug('ajax_TestQs:',['test' => $request->test]);
			if (!is_numeric($request->test)) $test=explode(":",$request->test)[0];
			else $test=$request->test;
			if($test){
				$test = $this->get_test($test);
				return response()->json(['test'=>$test]);
			}
			else return response()->json(null);
		}
	}
	
	public function ajax_log_event(Request $request)
	{
		Log::info(' log_event('.$request->paper.','.$request->question.','.$request->event.')');
		$request->user()->logs()->create([
				'event'=>$request->event,
				'paper'=> $request->paper,
				'question'=>$request->question,
				'answer'=>$request->has('answer')?$request->answer:'',
				'comment'=>$request->has('comment')?$request->comment:'',
				'variables'=>$request->has('vars')?json_encode($request->vars):''
		]);
		return response()->json("success");
	}
	
	public function ajax_QT(Request $request)
	{
		if($request->ajax() && ($question = $request->question)){
			Log::debug('ajax_QT:',['qId'=>$question['id'],'tId'=>$request->testId]);
			$q = new Question;
			if ($question['id'] != '') $prev = Question::find($question['id']);
			else $prev = null;
			unset($question['number']); // stored on test_question
			//unset($question['marks']); // stored on test_question
			$q->json = json_encode($question);
			$q->user()->associate($request->user());
			//$q->user_id = $request->user()->id;
			if ($question['id'] != '') $q->previous()->associate($prev);
			$q->save(); // beware json will have old or no id
			if ($prev != null) {
				$q->previous()->associate($prev); // regardless of user
				if ($prev->user && $prev->user->id == $request->user()->id)
				{
					$prev->next()->associate($q); // only if owner
					$prev->save();
				}
			}
			$question['id'] = $q->id;
			$q->json = json_encode($question);
			$q->save(); // json id corrected
			if ($prev != null) DB::table('question_test')
			->where('question_id',$prev->id)
			->update(['question_id' => $q->id]);
			return response()->json($question);
		}
	}
	
	public function ajax_bookQS(Request $request)
	{
		$t = null;
		if($request->ajax() && isset($request->id) && $book = Test::find($request->id))
		{
			Log::debug('ajax_bookQS:',['id'=>$request->id,'json'=>$book->json]);
			$t = json_decode($book->json);
			$t->id = $request->id;
			$t->type = $book->type;
			$t->title = $book->title;
				
			$marks=StatLog::where(['paper'=>$request->id,'user_id'=>$request->user()->id,'event'=>'✓✗'])->get();
				
			return response()->json(['book'=>$t,'marks'=>$marks]);
		}
	}
	
	public function ajax_test(Request $request)
	{
		$t = null;
		if($request->ajax())
		{
			if ($ret = $this->save_test($request->test,$request->questions,$request->user())) return response()->json(['id'=>$ret]);
		}
	}
	
}
