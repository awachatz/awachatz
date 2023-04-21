<?php

namespace App\Http\Controllers\Auth\User;


use App\{
    Http\Requests\UserRequest,
    Http\Controllers\Controller,
    Repositories\Front\UserRepository
};
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class RegisterController extends Controller
{

    /**
     * Constructor Method.
     *
     * Setting Authentication
     *
     * @param  \App\Repositories\Back\UserRepository $repository
     *
     */
    public function __construct(UserRepository $repository)
    {
        $this->repository = $repository;
    }


    public function showForm()
    {
      return view('user.auth.register');
    }


    public function register(UserRequest $request)
    {   
        $request->validate([
            'email' => 'required|email|unique:users,email'
        ]);

        $response = $this->repository->register($request);
        if ($response['account_created'] /*&& $response['success']*/) {
            Session::flash('success', __('Account Register Successfully please login'));
            return redirect()->back();
        }

        /*
        //if($this->repository->register($request)){
          //  Session::flash('success',__('Account Register Successfully please login'));
            $servername = "162.254.214.128";
            $username = "anywhereanycity_anywhereanycity_aws_support";
            $password = "mM$^2aWw4f#y";
            $dbname = "anywhereanycity_aws_support";
            $dbh = new PDO('mysql:host=localhost;dbname='.$dbname.'', $username, $password);
           // $conn = new mysqli($servername, $username, $password, $dbname);
            die();
            if ($conn->connect_error) {
              die("Connection failed: " . $conn->connect_error);
            }
            $user=$request['email'];
            $pass=md5($request['password']);
            $today=date('Y-m-d H:i:a');
            $contacnumber=$request['phone'];
           echo $sql = "INSERT INTO app_user (pvid, id, user, title, email, pass, role, panel, status, add_date, contact_number, img_url, tzone, gender, address, region, city, zip, country, dob, is_enable_chat)
            VALUES ('AA', 'AA', $user,$user,$user,$pass,'R1','A','A',$today,$contacnumber,' ','Asia/Dhaka','M','test',' ','Dhaka','1217',' ','0000-00-00','Y')";
           die(); 
            if ($conn->query($sql) === TRUE) {
              return redirect()->back();
            }
        }*/
        //$this->repository->register($request);
        
        
        
    }
    


    public function verify($token)
    {
        $user = User::where('email_token',$token)->first();
       
        if($user){
            
            Auth::login($user);
            
            return redirect(route('user.dashboard'));
        }else{
            return redirect(route('user.login'));
        }
    }



}
