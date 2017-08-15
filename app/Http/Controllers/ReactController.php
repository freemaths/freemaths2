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
	
	public function question(Request $request)
	{		
			return view('react',['scripts' => ['reactQ']]);
	}
	
	public function tests(Request $request)
	{
		return view('react',['scripts' => ['reactTestEditQs','reactTestEdit']]);
	}
	
	public function test(Request $request,$topic)
	{
		return view('react',['scripts' => ['reactTest'], 'params'=>['test'=>ucfirst($topic)]]);
	}
	
	public function edit(Request $request)
	{
		return view('react',['scripts' => ['reactEditQs']]);
	}

	public function graphApp(Request $request)
	{
		return view('reactapp');
	}
	
	public function reactApp(Request $request)
	{
		return view('reactapp',['scripts' => ['reactApp']]);
	}
	
	public function index(Request $request)
	{
		return view('reactapp');
	}
	
	public function ajax_Qs(Request $request)
	{
		//
		//$qs = Question::whereRaw("MATCH json AGAINST ('?' IN NATURAL LANGUAGE MODE)", array('negatives'))->get();
		Log::debug('ajax_Qs:',['filter' => $request->filter,'testId'=>$request->testId]);
		if($request->ajax()){
			if ($request->filter) $qs = Question::find_keywords($request->filter);
			else if ($request->testId) $qs =Test::find($request->testId)->questions()->orderBy('pivot_number')->get();
			else $qs = Question::all();
			$ret = [];
			foreach ($qs as $q)
			{
				$question = json_decode($q->json);
				if (isset($q->pivot)) {
					$question->number = $q->pivot->number;
					$question->marks = $q->pivot->marks;
				}
				$question->id = $q->id; // override what is in json
				$ret[] = $question;
			}
			return response()->json($ret);
		}
	}
	
	public function ajax_TestQs(Request $request)
	{
		//
		//$qs = Question::whereRaw("MATCH json AGAINST ('?' IN NATURAL LANGUAGE MODE)", array('negatives'))->get();
		Log::debug('ajax_TestQs:',['test' => $request->test]);
		if (is_numeric($request->test)) $test = Test::find($request->test);
		else $test=Test::find(explode(":",$request->test)[0]);
		if($request->ajax() && $test){
			$qs = $test->questions()->orderBy('pivot_number')->get();
			$ret = [];
			foreach ($qs as $q)
			{
				$question = json_decode($q->json);
				if (isset($q->pivot)) {
					$question->number = $q->pivot->number;
					unset($question->q);
					if ($q->pivot->marks > 0 || !isset($question->marks)) $question->marks = $q->pivot->marks;
				} else $question->number = 0;
				$question->id = $q->id; // override what is in json
				$ret[] = $question;
			}
			$test->qs=$ret;
			return response()->json(['test'=>$test]);
		}
		else return response()->json(null);
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
	
	public function ajax_negatives(Request $request)
	{
		$qs = json_decode(Storage::get('past/Negatives_questions.json'),true);
		//Log::debug('ajax_negatives:'.print_r($qs,true));
		if($request->ajax()){
			return response()->json($qs);
		}
	}
	
	public function ajax_fractions(Request $request)
	{
		//if ($q = Question::find(48)) return response()->json([$q]);
		$qs = json_decode(Storage::get('past/Fractions_questions.json'),true);
		//Log::debug('ajax_negatives:'.print_r($qs,true));
		if($request->ajax()){
			return response()->json($qs);
		}
	}
	
	public function ajax_stats(Request $request)
	{
		if($request->ajax()){
			if (!$request->last)
			{
				$tests=Test::all();
				$qmap=DB::table('question_test')->get();
			}
			if ($request->students && $students=$request->user()->students($request->all))
			{
				Log::debug('ajax_stats:',$students);
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
			else return response()->json(['log'=>$log,'users'=>$users,'tests'=>$tests,'qmap'=>$qmap]);
		}
	}
	
	/*
	public function ajax_stats(Request $request)
	{
		Log::debug('ajax_stats:'.(isset($request->students)?"students":""));
		if($request->ajax()){
			if ($request->all)
			{
				if (($students=User::all(['id','name','updated_at'])) && $request->user()->id == 1)
				{
					foreach ($students as $s)
						$ret[$s['id']]=['name'=>$s['name'],'updated_at'=>$s['updated_at'],
								'stats'=>StatLog::where('logs.user_id', $s['id'])
								->join('tests', 'logs.paper', '=', 'tests.id')
            					->select('logs.*', 'tests.title', 'tests.type')
            					->orderBy('id', 'asc')->get()];
				}
				else $ret = null;
			}
			else if ($request->students)
			{
				if ($students=$request->user()->students())
				{
					foreach ($students as $sId)
						$ret[$sId]=['name'=>User::find($sId)->name,'updated_at'=>User::find($sId)->updated_at,
								'stats'=>StatLog::where('logs.user_id', $sId)				
								->join('tests', 'logs.paper', '=', 'tests.id')
            					->select('logs.*', 'tests.title', 'tests.type')
            					->orderBy('id', 'asc')->get()];
				}
				else $ret = null;
			}
			else 
			{
				$ret[0]=['','stats'=>StatLog::where('logs.user_id', $request->user()->id)
				->join('tests', 'logs.paper', '=', 'tests.id')
            	->select('logs.*', 'tests.title', 'tests.type')
            	->orderBy('id', 'asc')->get()];
			}
			return response()->json($ret);
		}
	}
	*/
	
	public function ajax_update(Request $request)
	{
		if($request->ajax()){
			$question = $request->question;
			Log::debug('ajax_Q:'.$question['id']);
			if (isset($question['delete']))
			{
				if ($question['delete']>0) DB::table('questions')->where('id',$question['delete'])->delete();
			}
			else
			{
				if (starts_with($question['id'],'X_'))
				{
					$question['id'] = DB::table('questions')->insertGetId(['json' => json_encode($question)]);
					// insert blank to get new id;
				}
					
				DB::table('questions')
				->where('id', $question['id'])
				->update(['json' => json_encode($question)]);
			}
			return response()->json($question);
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
	
	public function ajax_help_save(Request $request)
	{
		Log::info('ajax_help_save('.$request->title.')');
		if ($request->ajax())
		{
			$help = new Help;
			$help->user()->associate($request->user());
			$help->title = $request->title;
			$help->text = $request->text;
			if ($oldhelp = Help::where([['title',$request->title],['next_id',0],['user_id',$request->user()->id]])->first())
			{
				$help->previous()->associate($oldhelp);
			}
			$help->save();
			if ($oldhelp) {
				$oldhelp->next()->associate($help);
				$oldhelp->save();
			}
			if ($request->id)
			if ($store) Storage::put('help/' . "{$help->title}.{$help->id}.json",json_encode($help));
			return response()->json(['id'=>$help->id]);
		}
		
	}
	
	
	private function save_help($h,$user)
	{
		Log::info('help_save('.$h['title'].')');
		$help = new Help;
		$help->user()->associate($user);
		$help->title = $h['title'];
		$help->text = $h['text'];
		if ($oldhelp = Help::where([['title',$h['title']],['next_id',0],['user_id',$user->id]])->first())
		{
			$help->previous()->associate($oldhelp);
		}
		else $help->previous_id = 0;
		$help->next_id = 0;
		$help->save();
		if ($oldhelp) {
			$oldhelp->next()->associate($help);
			$oldhelp->save();
		}
		Storage::put('help/' . "{$help->title}.{$help->id}.json",json_encode($help));
		return $help->id;
	}
	
	
	public function ajax_help(Request $request,$topic,$user_id=1)
	{
		//$this->log_event($request,ucwords(str_replace('_',' ',$topic)));
		Log::info('ajax_help('.$topic.')');
		if ($request->ajax())
		{
			$help = Help::where([['title',$topic],['next_id',0],['user_id',$user_id]])->first();
			if (!$help) $help=Help::where([['title',ucwords(str_replace('_',' ',$topic))],['next_id',0],['user_id',$user_id]])->first();
			if ($help) return response()->json($help);
			else return response()->json(['text'=>"Help Not Found",'title'=>$topic]);
		}
	}
	
	
	//$help = Storage::get('help/' . $topic . '.html');
	//return response()->json(['body'=>$help,'title'=>ucwords(str_replace('_',' ',$topic))]);
	
	/*
	public function log_event(Request $request,Question $q,$event)
	{
		$request->user()->logs()->create([
				'answer'=>$request->has('answer')?$request->answer:'',
				'comment'=>$request->has('comment')?$request->comment:'',
				'event'=>$event,
				'paper'=> $q->paper,
				'question'=>$q->question,
				'variables'=>$q->vars()?json_encode($q->vars()):''
		]);
	}
	*/
	
	
	public function questions()
	{
		$qs = Question::all();
		$ret = [];
		foreach ($qs as $q) $ret[] = json_decode($q->json);
		return json_encode($ret);
	}
	
	public function ajax_Q(Request $request)
	{
		if($request->ajax() && ($question = $request->question)){
			$question = $request->question;
			Log::debug('ajax_Q:'.print_r($question,true));
			if (isset($question['delete']))
			{
				if ($question['delete']>0) Question::find($question['delete'])->delete();
				return response()->json($question);
			}
			else
			{
				if ($question['id'] != '') $q = Question::find($question['id']);
				else {
					$q = new Question;
					$q->user()->associate($request->user());
				}
				unset($question['number']); // stored on test_question
				//unset($question['marks']); // stored on test_question
				$q->json = json_encode($question);
				$q->user()->associate($request->user());
				$q->save(); // json id corrected
				$question['id']=$q->id;
				return response()->json($question); // not sure we should return it
			}
		}
	}
	
	/*
	public function ajax_Q(Request $request)
	{
		if($request->ajax() && ($question = $request->question)){
			$question = $request->question;
			Log::debug('ajax_Q:'.print_r($question,true));
			if (isset($question['delete']))
			{
				if ($question['delete']>0) Question::find($question['delete'])->delete();
				return response()->json($question);
			}
			else 
			{
				$q = new Question;
				if (starts_with($question['id'],'X')) $question['id']='';
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
				// update tests with new version
				if ($prev != null && $prev->user && $prev->user->id == $request->user()->id) 
					DB::table('question_test')
					->where('question_id',$prev->id)
					->update(['question_id' => $q->id]);
				return response()->json($question);
			}
		}
	}
	*/
	
	
	private function save_QS($qs,$user)
	{
		$ret = [];
		foreach ($qs as $question){
			$saved['number']=$question['pivot']['number'];
			if ($question['pivot']['marks'] > 0 || !isset($question['marks'])) $saved['marks']=$question['pivot']['marks'];
			else $saved['marks']=$question['marks'];
			unset($question['pivot']); // stored on test_question
			unset($question['id']); // importing
			$q = new Question;
			$q->json = $question['json'];
			$q->user()->associate($user);
			$q->save(); // beware json will have old or no id
			$saved['id']=$q->id;
			$ret[]=$saved;
		}
		return $ret;
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
	
	public function replace_Q($tId,$qId,$newQId)
	{

	}
	
	private function save_test($test,$qs,$user,$store=true)
	{
		if ($test)
		{
			Log::debug('ajax_test:'.print_r($test,true));
			if ($store && $test['id'] != '') $t = Test::find($test['id']);
			if (@$t && $t->user->id == $user->id) // don't use user()->id on relationship
			{
				// nothing special
			}
			else
			{
				$t = new Test;
				$t->user()->associate($user);
			}
			$t->keywords = @$test['keywords'];
			$t->title = $test['title'];
			$t->type = $test['type'];
			$t->json = @$test['json'];
			$t->save();
			if ($questions = $qs)
			{
				$sync = array();
				for ($i=1; $i<= count($questions); $i++) $sync[$questions[$i-1]['id']] =  ['number' => $i,'marks'=>$questions[$i-1]['marks']];
				$t->questions()->sync($sync);
				$t['qs'] = $t->questions()->orderBy('pivot_number')->get();	
			}
			if ($store) Storage::put('tests/' . "{$t->title}.{$t->id}.json",json_encode($t));
			return $t->id;
		}
		return false;
	}
	
	private function save_past($test,$user,$store=true)
	{
		if ($test)
		{
			Log::debug('ajax_past');
			$t = new Test;
			$t->user()->associate($user);
			$t->keywords = $test['name'];
			$t->title = $test['title'];
			$t->type = $test['type'];
			$t->save();
			if ($questions = $this->save_past_qs($test['qs'],$user))
			{
				$sync = array();
				for ($i=1; $i<= count($questions); $i++) $sync[$questions[$i-1]['id']] =  ['number' => $i];
				$t->questions()->sync($sync);
				$t['qs'] = $t->questions()->orderBy('pivot_number')->get();
			}
			unset ($test['qs']);
			$t->json = json_encode($test);
			if ($store) Storage::put('tests/' . "{$t->title}.{$t->id}.json",json_encode($t));
			return $t->id;
		}
		return false;
	}
	
	private function save_past_qs($qs,$user)
	{
		$ret = [];
		$i=0;
		foreach ($qs as $question){
			$saved['number']=$i++;
			$saved['marks']=$question['marks'];
			$q = new Question;
			$q->json = json_encode($question);
			$q->user()->associate($user);
			$q->save(); // beware json will have old or no id
			$saved['id']=$q->id;
			$ret[]=$saved;
		}
		return $ret;
	}
	
	public function ajax_test(Request $request)
	{
		$t = null;
		if($request->ajax())
		{
			if ($ret = $this->save_test($request->test,$request->questions,$request->user())) return response()->json(['id'=>$ret]);
		}
	}
	
	public function ajax_help_list(Request $request)
	{
		if($request->ajax() && ($rows = Help::where([['next_id',0],['user_id',1]])->get(['id','title'])))
			return response()->json($rows);
	}
	
	public function ajax_migrate_list(Request $request)
	{
		if($request->ajax() && ($rows = PastPaper::all()))
			return response()->json($rows);
	}
	
	public function ajax_migrate_qs(Request $request)
	{
		if ($request->ajax())
		{
			if (isset($request->test['qs']))
			{
				return response()->json(['id'=>$this->save_past($request->test,$request->user())]);
			}
			else if ($name = $request->test['paper'].'_'.$request->test['year'].'_'.$request->test['month'])
			{
				while (ends_with($name,'_')) $name=substr($name,0,strlen($name)-1);
				$paper = json_decode(Storage::get("past/converted/{$name}.json"),true);
				return response()->json(['test'=>$paper]);
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
						Log::debug('ajax_import:',['oldid'=>$t['id']]);
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
	
	public function ajax_past(Request $request)
	{
		$t = null;
		if($request->ajax())
		{
			if ($test = @$request->test)
			{
				Log::debug('ajax_past:'.print_r($test,true));
				if (@$test['id'] != '') $t = Test::find($test['id']);
				if (@$t && $t->user->id == $request->user()->id) // don't use user()->id on relationship
				{
					// nothing special
				}
				else
				{
					$t = new Test;
					$t->user()->associate($request->user());
				}
				$t->keywords = $test['name']." ".$test['board']." ".$test['month']." ".$test['year'];
				$t->json = json_encode($test);
				$t->title = $test['name']."_".$test['board']."_".$test['month']."_".$test['year'];
				$t->type="past";
				$t->save();
				Storage::put('tests/' . "{$t->title}.{$t->id}.json",json_encode($t));
			}
			return response()->json(['id'=>$t['id']]);
		}
	}
	
	public function ajax_book(Request $request)
	{
		$t = null;
		if($request->ajax())
		{
			if ($book = @$request->book)
			{
				//Log::debug('ajax_book:'.print_r($test,true));
				if (@$book['id'] != '') $t = Test::find($book['id']);
				if (@$t && $t->user->id == $request->user()->id) // don't use user()->id on relationship
				{
					// nothing special
				}
				else
				{
					$t = new Test;
					$t->user()->associate($request->user());
				}
				$t->keywords = $book['name'];
				$t->json = json_encode($book);
				$t->title = $book['name']."_".$book['board'];
				$t->type="book";
				$t->save();
				Storage::put('books/' . "{$t->title}.{$t->id}.json",json_encode($t));
			}
			return response()->json(['id'=>$t['id']]);
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
	
	public function ajax_books(Request $request)
	{
		if ($request->ajax())
		{
			return response()->json(['books'=>Test::where('type','book')->get()]);
		}
	}
	
	public function ajax_marking(Request $request)
	{
		if($request->ajax())
		{
			$parts=explode(":",$request->test_id);
			$tid=$parts[0];
			Log::debug('ajax_marking:',['test_id'=>$request->test_id,'tid'=>$tid,'info'=>json_encode($request->info)]);
			if (($info=$request->info) && $tid && $test = Test::find($tid))
			{
				$log = $request->user()->logs()->create([
						'event'=>'✓✗',
						'paper'=> $request->test_id,
						'question'=>0,
						'answer'=>'',
						'comment'=>'',
						'variables'=>json_encode($info)
				]);		
				return response()->json(['log'=>$log]);
			}	
		}
	}
	
	public function ajax_marks(Request $request)
	{
		if($request->ajax())
		{
			Log::debug('ajax_marks:',['test'=>$request->test,'marking'=>$request->marking]);
			if (($test = Test::find($request->test)) && ($marks = StatLog::find($request->marking)))
			{
				return response()->json(['test'=>$test,'marks'=>$marks]);
			}
		}
	}
	
	private function save($id,$test_id,$marks)
	{
		$json=json_encode($marks);
		//Marking::where(['id'=>$id])->update(['json' => $json]);
		StatLog::where(['paper'=>"$test_id",'answer'=>"$id"])->update(['variables' => $json]);
	}
	
	private function convert($marks)
	{
		if (($test=Test::find($marks->test_id)) && $test['type'] == 'past')
		{
			$test = json_decode($test['json'],true);
			$mks = json_decode($marks['json'],true);
			$t=['marks'=>0,'total'=>0,'f'=>[':)'=>0,':|'=>0,':('=>0],'m'=>['✓'=>0,'?✓'=>0,'✗'=>0],'qs'=>0,'qc'=>0,'t'=>0];
			$ms=[];
			$t['qc']=count($test['questions']);
			for ($j=0; $j<$t['qc']; $j++)
			{
				if (isset($mks['marks'][$j]))
				{
					$m=[];
					$m['mk'] =  $mks['marks'][$j]['m'];
					$m['mks'] = $test['questions'][$j]['marks'];
					$m['q'] = $test['questions'][$j]['q'];
					$m['f'] =  $mks['marks'][$j]['f'];
					$m['c'] =  $mks['marks'][$j]['c'];
					if ($m['mk']==$m['mks']) {$m['m']='✓';$f=':)';}
					else if ($m['mk']=='0') {$m['m']='✗';$f=':(';}
					else {$m['m']='?✓';$f=':|';}
					if ($m['f'] == '') $m['f']=$f; // set to default 
					$ms[]=$m;
					
					$t['qs']++;
					$t['total']+=$m['mks'];
					$t['marks']+=$m['mk'];
					$t['f'][$m['f']]++;
					$t['m'][$m['m']]++;
				}
				else $ms[]=null;
			}
			$ret = (['marks'=>$ms,'total'=>$t]);
			$this->save($marks->id,$marks->test_id,$ret);
		}
		else $ret = (['marks'=>null,'error'=>"$test[id] is book - delete marks $marks[id]"]);
		return $ret;
	}
	
	
	public function convert_markings(Request $request)
	{
		$ms = Marking::all();
		$conv=[];
		foreach ($ms as $m)
		{
			$marks=json_decode($m->json,true);
			if (!isset($marks['total']) || !isset($marks['total']['qc'])) $conv[]=['test'=>$m->test_id,'id'=>$m->id,'user'=>$m->user_id,'old'=>$marks,'new'=>$this->convert($m)];
		} 
		return view('conv',['conv' => $conv]);
	}
	
	public function convert_markings2(Request $request)
	{
		$conv=[];
		if ($rows = StatLog::where('event','✓✗')->get())
		{
			foreach ($rows as $row)
			{
				$old=$row->variables;
				$info=json_decode($row->variables,true);
				unset($info['total']);
				$marks=[];
				foreach ($info['marks'] as $mark)
				{
					if ($mark['m']=='✗' && $mark['f']==':(') $mark['f']=':)';
					else if ($mark['m']=='?✓' && $mark['f']==':|') $mark['f']=':)';
					if ($row['answer'] != '') $marks[]=$mark;
					else if (isset($mark['show']) && $mark['show']) {unset($mark['show']); $marks[]=$mark;}
					else $marks[]=null;
				}
				$info['marks']=$marks;
				if (isset($info['effort']) && $info['effort']==0) unset ($info['effort']);
				$row->variables=json_encode($info);
				if ($old != $row->variables) $conv[]=['old'=>$old,'new'=>$row->variables];
				//$row->save(); // also Log
			}
			return view('conv2',['conv' => $conv]);
		}
	}
	
	
	public function ajax_test_add_question(Request $request)
	{
		$test = null;
		if($request->ajax())
		{
			if (($testId = @$request->testId) && ($questionId = @$request->questionId))
			{
					$test = Test::find($testId);
					$test->questions()->attach($questionId,['number'=>$test->questions()->count()+1,'marks'=>1]);
					$test->save();
			}
			return response()->json($test);
		}
	}	
	
}
