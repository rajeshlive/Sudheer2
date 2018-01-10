<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Http\Requests\SaveQuoteRequest;
use App\Http\Controllers\Controller;
use App\Repositories\QuoteRepository;
use App\Repositories\UserRepository;

use Notification;
use Storage;

use HighriseAPI;
use HighrisePerson;
use HighriseCustomfield;
use HighriseNote;

use App\Quote;
use App\User;
use App\Testimonial;
//use App\SalesPerson;

use Illuminate\Support\Facades\Mail;

class QuoteController extends Controller
{
 
     public function __construct(QuoteRepository $repository)
    {
        $this->repository = $repository;

        // Access control...
        //$this->middleware('public_page', ['only' => ['create']]);
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create(Request $request)
    {   



            switch($request->route()->getName()) {
                case 'build':
                    $steps = 8;
                break;
                case 'quick':
                   $steps = 2;
                break;
            }

        view()->share('steps',$steps);

        return view('quote/create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(SaveQuoteRequest $request)
    {


        //dd($request->all());
        //Get Dollar Values

        //Save to Highrise

        //dd($request->all());

        //Find Salesperson
        // $salesperson = "";
        // $state = $request->get('state');

        // $findsp = SalesPerson::where('states','like','%'.$state.'%')->first();
        // if(count($findsp)>0) {

        //     // $salesperson['name'] = $findsp->firstname ." ".$findsp->lastname;
        //     // $salesperson['email'] = $findsp->email ." ".$findsp->lastname;
        //     // $salesperson['phone'] = $findsp->firstname ." ".$findsp->lastname;

        //     // dd($findsp);
        // } else {
        //     //Find default
        //     dd('not found');
        // }

        



        $highrise = new HighriseAPI();
        $highrise->setAccount(env('HIGHRISE_API_NAME'));
        $highrise->setToken(env('HIGHRISE_API_KEY'));

        $person = new HighrisePerson($highrise);
        $person->setFirstName($request->get('firstname'));
        $person->setLastName($request->get('lastname'));
        $person->addEmailAddress($request->get('email'));
        
        $person->setCustomField( 'where_will_your_elevator_be_installed',  $request->get('state'));
        $person->setCustomField( 'time_frame_for_your_elevator_project',  $request->get('timeframe') ? $request->get('timeframe') : 'N/A');
        

        switch($request->get('type'))
        {
            case 'build':
                $person->addTag('New Leads (Quote Form)');
            break;
            case 'quick':
                $person->addTag('New Leads (Contact Us Form)');
            break;
        }
        


        
        $person->save();

        $note = new HighriseNote($highrise);
        $note->setSubjectType("Party");
        $note->setSubjectId($person->getId());

        $note_text = "Custom Quote for " .$request->get('firstname')." ".$request->get('lastname'). " - " .$request->get('email');
        if( $request->get('phone') ) {
            $note_text .= " - " .$request->get('phone');
        }

        $note_text .= "\n\n";

        if($request->get('referrer')) $note_text .= "Referred by: " .ucfirst($request->get('referrer'));
        
        if($request->get('adwords')) {
            $note_text .= "\n" ."Adwords: Yes";
        } else {
            $note_text .= "\n" ."Adwords: No";
        }

        if($request->get('type')) {
            $note_text .= "\n" ."Quote Type: " . ucfirst($request->get('type'));
        }

        $note_text .= "\n" ."Source: Wiltek";

        $prices = [
                'stops' => [
                    '2' => ['price' => 13495, 'label' => '2 Stops - (Example - From Floor 1 to Floor 2) (Standard)'],
                    '3' => ['price' => 14495, 'label' => '3 Stops - (Example - From Floor 1 to Floor 2 to Floor 3) (Additional)'],
                    '4' => ['price' => 15495, 'label' => '4 Stops - (Example - From Floor 1 to Floor 2 to Floor 3 to Floor 4) (Additional)'],
                    ],
                'lineup' => [
                    'frontonly' => ['price' => 0, 'label' => 'Front Only (One door / gate) (Standard)'],
                    'frontrear' => ['price' => 800, 'label' => 'Front and Rear (Two doors / gates) (Additional)'],
                    'frontside' => ['price' => 800, 'label' => 'Front and Side (Two doors / gates) (Additional)'],
                ],
                'cabheight' => [
                    '6-10' => ['price' => 0, 'label' => '6\'10" Cab Height (Standard)'],
                    '8-0' => ['price' => 750, 'label' => '8\'0" Cab Height (Additional)'],
                ],
                'fixtures' => [
                    'stainlessfixtures' => ['price' => 0, 'label' => 'Brushed Stainless Steel Fixtures (Standard)'],
                    'anodizedfixtures' => ['price' => 250, 'label' => 'Anodized Bronze Fixtures (Additional Cost)'],
                ],
                'gates' => [
                    'blackaccordion' => ['price' => 0, 'label' => 'Black Laminate Accordion Car Gate (Standard)'],
                    'blackscissor' => ['price' => 995, 'label' => 'Black Scissor Gate (Additional)'],
                    'smokedaccordion' => ['price' => 575, 'label' => 'Smoked Acrylic Accordion Gate (Additional)'],
                    'clearacrylic' => ['price' => 575, 'label' => 'Clear Acrylic Car Gate (Additional)'],
                    'hardwood' => ['price' => 1000, 'label' => 'Hardwood Gates (Additional)'],
                    'automatic' => ['price' => 1000, 'label' => 'Automatic Car Gate (Additional)'],
                ],
                'features' => [
                    'homepark' => ['price' => 100, 'label' => 'Home Park Feature'],
                    'autolight' => ['price' => 0, 'label' => 'Automatic Light Feature'],
                    'remote' => ['price' => 750, 'label' => 'Remote Machine Room Location Up to 25\' Away'],
                    'halogen' => ['price' => 250, 'label' => 'Halogen Lights'],
                    'arrester' => ['price' => 210, 'label' => 'Lightning Arrester / Surge Protector'],
                ],
                'warranty' => [
                    '1' => ['price' => 0, 'label' => 'One Year Warranty on Parts'],
                    '2' => ['price' => 350, 'label' => 'Two Year Warranty on Parts'],
                    '3' => ['price' => 500, 'label' => 'Three Year Warranty on Parts (Recommended)'],
                ],

            ];

       $message_html = "";

        if($request->get('type') == 'build') {

            $subject = "Here is your custom quote from ".config('app.name');

           $message_html .= "Custom Quote for " .$request->get('firstname') ." " .$request->get('lastname') ." - " .$request->get('email') ." - " .$request->get('phone');
           $message_html .= "<br><br>\n\n";

           //dd($request->all());

           if($request->get('new_existing_home')) $message_html .= "Is this elevator for an existing home or for new construction home? <br><strong>". $request->get('new_existing_home') ."</strong><br><br>\n";
           if($request->get('wheelchair')) $message_html .= "Is this elevator needed for a wheelchair? <br><strong>". $request->get('wheelchair') ."</strong><br><br>\n";
           if($request->get('retrofit_stairs')) $message_html .= "If this is a retrofit / remodel to an existing home, will you be removing the stairs? <br><strong>". $request->get('retrofit_stairs') ."</strong><br><br>\n";

           $message_html .= "Where will your elevator be installed? <br><strong>". $request->get('state') ."</strong><br><br>\n";
           $message_html .= "What is your timeframe for this elevator project? <br><strong>". $request->get('timeframe') ."</strong><br><br>\n";

           

           $total = "";

           foreach($request->all() as $k => $value) {
                foreach($prices as $price => $p) {
                    
                    if($k == $price) {
                        if(is_array($value)) {
                            
                            foreach($value as $subk => $subval) {
                                $message_html .= $prices[$k][$subk]['label'] ." - <br><strong>" .'$' .$prices[$k][$subk]['label'] ."</strong><br><br>\n";
                                $total += $prices[$k][$subk]['price'];
                            }

                        } else {
                            $message_html .= $prices[$k][$value]['label'] ." - <br><strong>" .'$'.$prices[$k][$value]['price'] ."</strong><br><br>\n";
                            $total += $prices[$k][$value]['price'];    
                        }
                        
                    }
                }    
           }

           
           $message_html .= "\n" ."Total <br><strong>" .'$' .number_format($total, 2) ."</strong><br><br>\n";

           $states = json_decode(Storage::get('state_prices.json'));

           foreach($states as $state) {

            if($request->get('state') == $state->state) {
                $message_html .= "\n\n" ."Estimated installation cost in " .$state->state ."<br><strong> ".$state->min." - " .$state->max ."</strong><br><br>\n";
            }

           }

           $page_content['title'] = $subject;
           $page_content['content'] = "<p>Thanks for your inquiry with Wiltek, one of America's most trusted names in Home Elevators.  Below you'll have your customized quote based on the information you provided our team.  We'll be in contact shortly to review your quote and answer any additional questions that you might have.<p>";
            $page_content['footer'] = "<p>Thanks, <br><strong>Your Wiltek Team</strong></p>";

            $page_content['quote'] = '<em>"Customer service is not a department, it’s our entire business. Each and every day we strive to make our customer experience a little bit better."</em><br> - Your Wiltek Team';


        } 


        if($request->get('type') == 'quick') {

            $subject = "Thank you for contacting ". config('app.name');

            // $message_html .= "\n\n";

            // $message_html .= $request->get('firstname') .", thank you for your interest in ".config('app.name');

            // $message_html .= "\n\n";
           

            //Existing
            //$message_html .= "Is this elevator for an existing home or for new construction home? ". $request->get('new_existing_home') ."\n";
            //$message_html .= "Is this elevator needed for a wheelchair? ". $request->get('wheelchair') ."\n";
            //$message_html .= "How many stops do does the home elevator need?". $request->get('stops') ."\n";
            //$message_html .= "Where will your elevator be installed?". $request->get('state') ."\n";

            $page_content['title'] = $subject;
            $page_content['content'] = "<p>Thanks for your inquiry with Wiltek.  With more than 1,000 successful installations, we are America's leading provider of home elevator solutions.  We'll be in contact shortly to discuss your needs and answer any additional questions that you might have.<p>";
            $page_content['footer'] = "<p>Thanks, <br><strong>Your Wiltek Team</strong></p>";

            $page_content['quote'] = '<em>"Customer service is not a department, it’s our entire business. Each and every day we strive to make our customer experience a little bit better."</em><br> - Your Wiltek Team';

        }



        $note_text .= strip_tags($message_html);

        $note_text .= "\n\n";

        $note->setBody($note_text);
        $note->save();
        
        $calculate = "values";

        $fields = $request->all();
        
        $model = $this->repository->save($fields);

        //Send to User
        $message_html_filtered = explode("\n", e($message_html));
        $message_html_html = '<p>' . implode('</p><p>', array_filter($message_html_filtered, 'strlen')) . '</p>';

        $data['fields'] = $request->all();
        $data['message_html'] = $message_html;
        $data['page_content'] = $page_content;
        
        //To User
        Mail::send('emails.build_quote', ['data'=>$data], function($message) use ($data, $subject)
        {   

            $message->to($data['fields']['email'], $data['fields']['firstname'] ." ".$data['fields']['lastname'])->subject($subject);
        });

        $data['admin'] = true;



        //TO Admins
        $users = with(new UserRepository)->administrators();

        $view = 'emails.build_quote';
        $subject = 'Quote submission from '.$data['fields']['firstname'] ." " .$data['fields']['lastname'] .' in ' .$data['fields']['state'];

        foreach($users as $user) {
            if($user->primaryEmail()) {

                Mail::send($view, ['data'=>$data] , function($message) use ($user, $data, $subject)
                {
                    $message->to($user->primaryEmail()->address, $user->name)->subject($subject);
                });
            }
        }

        // $users->each(function($user) use ($view, $data, $subject) {
        //     if($user->primaryEmail()) {
        //         Mail::send($view, $data, function($message) use ($user, $subject)
        //         {
        //             $message->to($user->primaryEmail()->address, $user->name)->subject($subject);
        //         });
        //     }
        // });

        // flash()->message('Successfully created ' .$building->address);
        //Notification::success('Thank You for contacting us!');
        //print_r($request->all());
        return view('quote/thankyou')->with('message',$message_html)->with('page_content',$page_content);
    }

     public function thankyou()
    {
        return view('quote/thankyou');
    }

     public function getquote()
    {

        $testimonials = Testimonial::limit(3);
        $all = $testimonials->orderByRaw("RAND()")->get();
        view()->share('testimonials',$all);

        return view('quote/get');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
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
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
