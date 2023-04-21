<?php
/**
 * Created by UniverseCode.
 */

namespace App\Helpers;

use App\{
    Models\EmailTemplate,
    Models\Setting
};
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use PHPMailer\PHPMailer\{
    PHPMailer,
    Exception
};

class EmailHelper
{

    public $mail;
    public $setting;

    public function __construct()
    {
        $this->setting = Setting::first();

        $this->mail = new PHPMailer(true);

        if(true){

            $this->mail->isSMTP();
            $this->mail->Host= $this->setting->email_host;
            $this->mail->SMTPAuth   = true;
            $this->mail->Username   = $this->setting->email_user;
            $this->mail->Password   = $this->setting->email_pass;
            $this->mail->Port       = $this->setting->email_port;
            
            // $this->mail->Host=env("MAIL_HOST", "mail.awachatz.com");
            // $this->mail->SMTPAuth=true;
            // $this->mail->Username=env("MAIL_USERNAME", "infos@awachatz.com");
            // $this->mail->Password=env("MAIL_PASSWORD", "X{9xV64$&Q3]");
            // $this->mail->Port=env("MAIL_PORT", "465");
            
            // $this->mail->Host       = 'mail.awachatz.com';
            // $this->mail->SMTPAuth   =  true;
            // $this->mail->Username   = 'info@awachatz.com';
            // $this->mail->Password   = "k]R[N#3EwUar"
            // $this->mail->Port       = '465';

            // if (env('MAIL_ENCRYPTION') == 'ssl') {
                 $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            // } else {
                //  $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            // }
            
            
            $this->mail->CharSet        = 'UTF-8';

        }
    }

    public function sendTemplateMail(array $emailData)
    {
        $template = EmailTemplate::whereType($emailData['type'])->first();
        try{
            $email_body = preg_replace("/{user_name}/", $emailData['user_name'] ,$template->body);
            $email_body = preg_replace("/{order_cost}/", $emailData['order_cost'] ,$email_body);
            $email_body = preg_replace("/{transaction_number}/", $emailData['transaction_number'] ,$email_body);
            $email_body = preg_replace("/{site_title}/", $this->setting->title ,$email_body);

            $this->mail->setFrom('newsletter@awachatz.com', 'awachatz');
            $this->mail->addAddress($emailData['to']);
            $this->mail->isHTML(true);
            $this->mail->Subject = $template->subject;
            $this->mail->Body = $email_body;
            $this->mail->send();
        }


        catch (Exception $e){
           // dd($e->getMessage());
        }

        return true;

    }

    public function sendCustomMail(array $emailData)
    {
        try{

            $this->mail->setFrom(env("MAIL_FROM_ADDRESS"), env('MAIL_FROM_NAME'));
            $this->mail->addAddress($emailData['to']);
            $this->mail->isHTML(true);
            $this->mail->Subject = $emailData['subject'];
            $this->mail->Body = $emailData['body'];
            $this->mail->send();

        }
        catch (Exception $e){
           // dd($e->getMessage());
        }

        return true;
    }


    public static function getEmail()
    {
        $user = Auth::user();
        if(isset($user)){
            $email = $user->email;
        }else{
            $email = Session::get('billing_address')['bill_email'];
        }
        return $email;
    }

}
