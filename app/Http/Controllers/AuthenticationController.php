<?php

namespace App\Http\Controllers;

use App\Models\Activitie;
use App\Models\Booking;
use App\Models\Contact;
use App\Models\Email;
use App\Models\Enquire;
use App\Models\User;
use App\Models\Visa;
use Dirape\Token\Token;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthenticationController extends Controller
{
    public function register(Request $request)
    {
        $rules = array(
            "phone" => "required",
            "email" => "required|email",
            "name" => "required",
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $validator->errors();
        } else {
            $mobileNumber = $request->phone;
            // Remove whitespace and symbols
            $mobile = preg_replace('/[^0-9]/', '', $mobileNumber);
            if (User::where('email', $request->email)->first()) {
                return response(["status" => false, "message" => "Your Email ID is Already registered. Kindly Use Different Email Id to create a new account."], 200);
            }
            if (User::where('phone', $mobile)->first()) {
                return response(["status" => false, "message" => "Your Mobile. No is Already registered. Kindly Use Different Mobile No. to create a new account."], 200);
            }
            $user = User::where('phone', $mobile)->orwhere('email', $request->email)->first();
            if (!$user) {
                $user = new User();
                $user->email = $request->email;
                $user->phone = $request->phone;
                $user->name = $request->name;
                $user->passcode = $request->password;
                $user->role = $request->role;
                if (isset ($request->business_id)) {
                    $user->business_id = $request->business_id;
                }
                if (isset ($request->location_id)) {
                    $user->location_id = $request->location_id;
                }
                $user->save();
                return response(["status" => true, "message" => "User is verified sucessfully.", "data" => $user], 200);

            } else {
                return response(["status" => false, "message" => "Phone number or Email is already registered."], 200);
            }
        }
    }
    public function login(Request $request){
        $rules = array(
            "phone" => "required",
            "password" => "required",
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $validator->errors();
        } else {
            if (!User::where('phone', $request->phone)->first()) {
                return response(["status" => "failed", "message" => "User is not Registered."], 401);
            }
            $user = User::where('phone', $request->phone)->first();
            if (!($request->password == $user->passcode)) {
                return response(["status" => "failed", "message" => "Incorrect Password"], 401);
            } else {
                $response = [
                    'status' => true,
                    'user' => $user,
                    "message" => "User is Logged IN"
                ];
                return response($response, 200);
            }
        }
    }
    public function forgot(Request $request){
        $rules = array(
            "phone" => "required",
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $validator->errors();
        } else {
            if (!User::where('phone', $request->phone)->first()) {
                return response(["status" => "failed", "message" => "User is not Registered."], 401);
            }
            $user = User::where('phone', $request->phone)->first();
                $response = [
                    'status' => true,
                    'phone' => $user->phone,
                    'password' => $user->passcode,
                    "message" => "This is a password."
                ];
                return response($response, 200);
           
        }
    }
    public function updateprofile(Request $request)
    {
        $rules = array(
            "phone" => "required",
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return $validator->errors();
        } else {
            $mobileNumber = $request->phone;
            // Remove whitespace and symbols
            $mobile = preg_replace('/[^0-9]/', '', $mobileNumber);
            if (!User::where('phone', $mobile)->first()) {
                return response(["status" => false, "message" => "Your Mobile. No is Not registered."], 200);
            }
            $user = User::where('phone', $mobile)->first();
            if ($user) {
                $user->name = $request->name;
                $user->passcode = $request->password;
                $user->save();
                return response(["status" => true, "message" => "User is Updated sucessfully.", "data" => $user], 201);

            } else {
                return response(["status" => false, "message" => "Phone number is Not registered."], 200);
            }
        }
    }
    public function getstarted(Request $request)
    {
        $rules = array(
            "phone" => "required",
            "email" => "required|email",
            "name" => "required",
            // Add validation rules for other fields
        );
    
        $validator = Validator::make($request->all(), $rules);
    
        if ($validator->fails()) {
            return $validator->errors();
        } else {
            $user = User::where('mobile', substr($request->phone, -10))
                        ->Where('email', strtolower($request->email))
                        ->first();
                        try {
                        $client = new Client();
                        $headers = [
                          'Content-Type' => 'application/json'
                        ];
                        $body = '{
                          "userName": "aadarshkavita@gmail.com",
                          "userPassword": "Test@2023@#$",
                          "bookingDate": "'.$request->travelDate.'",
                          "attractionId": "'.$request->attractionId.'",
                          "ticketTypeId": "'.$request->ticketTypeId.'"
                        }';
                        $req = new Psr7Request('POST', 'https://parmartours.com:8443/parmartour/Test/getTimeSlotList', $headers, $body);
                        $res = $client->sendAsync($req)->wait();
                        $call = ($res->getBody()->getContents());
                    } catch (\Exception $e) {
                        echo 'Error: ' . $e->getMessage();
                    }
                    $call = json_decode($call, true);
            if (!$user) {
                
                $user = new User();
                $user->email = strtolower($request->email);
                $user->name = $request->name;
                $user->token = (new Token())->unique('users', 'token', 60);
                $user->mobile = substr($request->phone, -10);
                $user->save();
                $booking = new Booking();
                $booking->attractionId = $request->attractionId;
                $booking->ticketTypeId = $request->ticketTypeId;
                $booking->ticket_type_id =  $call['ticketTypeId'];
                $booking->userId = $user->id;
                $booking->nofAdult = $request->nofAdult;
                $booking->nofChild = $request->nofChild;
                $booking->nofInfant = $request->nofInfant;
                $booking->travelDate = $request->travelDate;
                $booking->save();
                return response([
                    'status' => true,
                    'book_id' => $booking->id,
                    'local_attraction_id' => $request->local_attraction_id,
                    'api' => $call->agentServiceEventsPrice,
                    'user' => $user,
                    "message" => "User Fetched"
                ], 200);
            } else {
                $booking = new Booking();
                $booking->attractionId = $request->attractionId;
                $booking->ticketTypeId = $request->ticketTypeId;
                $booking->userId = $user->id;
                $booking->ticket_type_id =  $call['ticketTypeId'];
                $booking->nofAdult = $request->nofAdult;
                $booking->nofChild = $request->nofChild;
                $booking->nofInfant = $request->nofInfant;
                $booking->travelDate = $request->travelDate;
                $booking->save();

                return response([
                    'status' => true,
                    'local_attraction_id' => $request->local_attraction_id,
                    'book_id' => $booking->id,
                    'user' => $user,
                    'api' => $call['agentServiceEventsPrice'],
                    "message" => "User Fetched"
                ], 200);
            }
        }
    }
    public function visaform(Request $request)
    {
        $rules = array(
            "mobile" => "required",
            "name" => "required",
            // Add validation rules for other fields
        );
    
        $validator = Validator::make($request->all(), $rules);
    
        if ($validator->fails()) {
            return $validator->errors();
        } else {
            
                $user = new Visa();
                $user->name = $request->name;
                $user->mobile = $request->mobile;
                $user->dob = $request->dob;
                $user->expiry_passport = $request->expiry_passport;
                $user->nationality = $request->nationality;
                $user->save();
                return response([
                    'status' => true,
                    'user' => $user,
                    "message" => "Visa Request Sent"
                ], 200);
        }
    }

    public function visafile(Request $request)
    {
        $rules = array(
            
        );
    
        $validator = Validator::make($request->all(), $rules);
    
        if ($validator->fails()) {
            return $validator->errors();
        } else {
            
                $user = new Visa();
                if ($request->hasFile('file')) {
                    $file = $request->file('file')->store('public/visa');
                    $user->file = $file;
                }
                $user->save();
                return response([
                    'status' => true,
                    'user' => $user,
                    "message" => "Visa File Uploaded"
                ], 200);
        }
    }
    public function createBooking(Request $request)
{
    $rules = array(
        "attractionId" => "required",
        "ticketTypeId" => "required",
        "userId" => "required",
        "nofAdult" => "required",
        "nofChild" => "required",
        "nofInfant" => "required",
        "travelDate" => "required",
        "bookingRefNumber" => "required",
        "childTickets" => "required",
        "adultTickets" => "required",
        "sessionId" => "required",
        // Add validation rules for other fields
    );

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return $validator->errors();
    } else {
        $booking = new Booking();
        $booking->attractionId = $request->attractionId;
        $booking->ticketTypeId = $request->ticketTypeId;
        $booking->userId = $request->userId;
        $booking->nofAdult = $request->nofAdult;
        $booking->nofChild = $request->nofChild;
        $booking->nofInfant = $request->nofInfant;
        $booking->travelDate = $request->travelDate;
        $booking->bookingRefNumber = $request->bookingRefNumber;
        $booking->childTickets = $request->childTickets;
        $booking->adultTickets = $request->adultTickets;
        $booking->sessionId = $request->sessionId;

        // If you have timestamps in your table, Eloquent will handle them automatically
        $booking->save();

        return response([
            'status' => true,
            'booking' => $booking,
            "message" => "Booking Created Successfully"
        ], 200);
    }
}
public function insertEnquiry(Request $request)
{
    $rules = array(
        'userId' => 'required',
        'attractionId' => 'required',
        'attractionName' => 'required',
        'ticketTypeId' => 'required',
        'ticketTypeName' => 'required',
        'bookingDate' => 'required',
        'eventTypeID' => 'required',
        'eventID' => 'required',
        'childPrice' => 'required',
        'adultPrice' => 'required',
        'available' => 'required',
        'resourceID' => 'required',
        'eventName' => 'required',
        'startDateTime' => 'required',
        'endDateTime' => 'required',
    );

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return $validator->errors();
    } else {
        $enquiry = new Enquire();
        $enquiry->userId = $request->userId;
        $enquiry->attractionId = $request->attractionId;
        $enquiry->attractionName = $request->attractionName;
        $enquiry->ticketTypeId = $request->ticketTypeId;
        $enquiry->ticketTypeName = $request->ticketTypeName;
        $enquiry->bookingDate = $request->bookingDate;
        $enquiry->eventTypeID = $request->eventTypeID;
        $enquiry->eventID = $request->eventID;
        $enquiry->childPrice = $request->childPrice;
        $enquiry->adultPrice = $request->adultPrice;
        $enquiry->available = $request->available;
        $enquiry->resourceID = $request->resourceID;
        $enquiry->eventName = $request->eventName;
        $enquiry->startDateTime = $request->startDateTime;
        $enquiry->endDateTime = $request->endDateTime;

        $enquiry->save();

        return response([
            'status' => true,
            'enquiry' => $enquiry,
            'message' => 'Enquiry Request Sent'
        ], 200);
    }
}
public function email(Request $request)
{
    $rules = array(
        "email" => "required|email",
        // Add validation rules for other fields
    );

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return $validator->errors();
    } else {
        $contact = new Email();
  
        $contact->email = $request->email;
  
        $contact->save();

        return response([
            'status' => true,
            'contact' => $contact,
            "message" => "We will contact you soon."
        ], 200);
    }
}
public function contactForm(Request $request)
{
    $rules = array(
        "name" => "required",
        "email" => "required|email",
        "mobile" => "required",
        "subject" => "required",
        "message" => "required",
        // Add validation rules for other fields
    );

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return $validator->errors();
    } else {
        $contact = new Contact();
        $contact->name = $request->name;
        $contact->email = $request->email;
        $contact->mobile = $request->mobile;
        $contact->subject = $request->subject;
        $contact->message = $request->message;
        $contact->save();

        return response([
            'status' => true,
            'contact' => $contact,
            "message" => "We will contact you soon."
        ], 200);
    }
}
public function createActivity(Request $request)
{
    $rules = array(
        'title' => 'required|string',
        'subtitle' => 'required|string',
        'overview' => 'required|string',
        'highlight' => 'required|string',
        'image_1' => 'required',
        'image_2' => 'required',
        'image_3' => 'required',
        'image_4' => 'required',
        'activity_id' => 'required',
        'ticket_type' => 'required',
    );

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return $validator->errors();
    } else {
        $activity = new Activitie();
        
        // Assuming you want to store images in the 'public/activites' directory
        if ($request->hasFile('image_1')) {
            $image1 = $request->file('image_1')->store('public/activites');
            $activity->image_1 = $image1;
        }

        if ($request->hasFile('image_2')) {
            $image2 = $request->file('image_2')->store('public/activites');
            $activity->image_2 = $image2;
        }

        if ($request->hasFile('image_3')) {
            $image3 = $request->file('image_3')->store('public/activites');
            $activity->image_3 = $image3;
        }

        if ($request->hasFile('image_4')) {
            $image4 = $request->file('image_4')->store('public/activites/4');
            $activity->image_4 = $image4;
        }

        // Assign other fields
        $activity->name = $request->input('name');
        $activity->title = $request->input('title');
        $activity->subtitle = $request->input('subtitle');
        $activity->overview = $request->input('overview');
        $activity->highlight = $request->input('highlight');
        $activity->activity_id = $request->input('activity_id');
        $activity->ticket_type = $request->input('ticket_type');
        $activity->save();
        return response([
            'status' => true,
            'activity' => $activity,
            'message' => 'Activity Created'
        ], 200);
    }
}

}
