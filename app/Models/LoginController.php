<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Customer;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Arr;
use DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Helpers\OtpHelper;
use Illuminate\Validation\Rule;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Exception\MessagingException;



class LoginController extends Controller
{

    public function login(Request $request)
    {

        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);


        // $user_count = Customer::where('mobile', $request->username)->count();
        $user_count = Customer::where(function ($query) use ($request) {
            $query->where('mobile', $request->username)
                ->orWhere('referrel_code', $request->username);
        })->count();



        if (Auth::attempt(array('email' => $request->username, 'password' => $request->password))) {

            $user = User::with('userSubscription')
                ->whereHas('userSubscription', function ($query) {
                    $query->where('is_closed', false);
                })
                ->where('email', $request->username)
                ->where('is_admin', false)
                ->first();

            $token = $user ? $user->createToken('mykuri-app-token')->plainTextToken : NULL;
            if (!$token || is_null($token)) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not exists for token is not created'
                ]);
            }

            $customer = Customer::where('user_id', $user->id)->first();
            if ($customer) {
                $customer->is_verified = '1';
                $customer->save();
            }
            $user_out = User::where('email', $request->username)->with('customer')->first();

            if ($user_out->customer->is_verified == '1') {

                return response([
                    'status' => '1',
                    'user' => $user_out,
                    'token' => $token,
                    'message' => 'Login Successful',
                ]);
            }
        } elseif ($user_count == '1') {


            $customer = Customer::where(function ($query) use ($request) {
                $query->where('mobile', $request->username)
                    ->orWhere('referrel_code', $request->username);
            })->first();


            if (!$customer || !Hash::check($request->password, $customer->password)) {
                return response([
                    'status' => '0',
                    'message' => 'Wrong Credentials',
                ]);
            } else {

                $customer = Customer::where('user_id',  $customer->user_id)->first();
                $customer->is_verified =  '1';
                $customer->update();
                $user = User::with('userSubscription')
                    ->whereHas('userSubscription', function ($query) {
                        $query->where('is_closed', false);
                    })
                    ->where('is_admin', false)->findOrFail($customer->user_id);

                $token = $user->createToken('mykuri-app-token')->plainTextToken;
                $user_out = User::where('email', $user->email)->with('customer')->first();
                if ($user_out->customer->is_verified == '1') {
                    return response([
                        'status' => '1',
                        'user' => $user_out,
                        'token' => $token,
                        'message' => 'Login Successful',
                    ]);
                }
            }
        } else {

            return response()->json([
                'message' => 'Invalid Login',
                'status' => '0'
            ]);
        }
    }

    public function verifyReferalCode(Request $request)
    {
        $request->validate([
            'referrel_code' => 'required',
        ]);

        $customer = Customer::where('referrel_code', $request->referrel_code);
        $customer_count = $customer->count();
        if ($customer_count == '1') {
            $customer_data = $customer->first();
            if ($customer_data->is_verified == "0") {
                //$fourDigitRandomNumber = rand(1000, 9999);
                // $fourDigitRandomNumber = '4345';
                $customer_data->otp =  OtpHelper::getOtp();
                $customer_data->is_verified =  '1';
                $customer_data->update();
                return response()->json([
                    'message' => 'User is verified successfully.Please Login to use App',
                    'status' => '1'
                ]);
            } else {
                return response()->json([
                    'message' => 'This Refferal Code is already verified.Try Login',
                    'status' => '0'
                ]);
            }
        } else {
            return response()->json([
                'message' => 'This Referrel Code is not exist.Please check your Referal Code',
                'status' => '0'
            ]);
        }
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'username' => 'required',
        ]);
        $user_count = User::where('email', $request->username)->count();
        if ($user_count == '1') {
            $user = User::where('email', $request->username)->first();
            //   $token = $user->createToken('mykuri-app-token')->plainTextToken;
            //$fourDigitRandomNumber = rand(1000, 9999);
            // $fourDigitRandomNumber = OtpHelper::getOtp();
            $customer = Customer::where('user_id', $user->id)->first();
            $customer->otp =  OtpHelper::getOtp();
            $customer->is_verified =  '0';
            $customer->update();
            $user_out = User::where('email', $user->email)->with('customer')->first();
            return response()->json([
                'message' => 'Please Verify the Account using Otp',
                //  'user' => $user_out,
                //  //     'token' => $token,
                'status' => '1'
            ]);
        } else {
            $customer_count = Customer::where('mobile', $request->username)->count();
            if ($customer_count == '1') {
                $customer = Customer::where('mobile', $request->username)->first();
                $user = User::where('id', $customer->user_id)->first();
                //    $token = $user->createToken('mykuri-app-token')->plainTextToken;
                //$fourDigitRandomNumber = rand(1000, 9999);
                $customer = Customer::where('user_id', $user->id)->first();
                $customer->otp =  OtpHelper::getOtp();
                $customer->is_verified =  '0';
                $customer->update();
                $user_out = User::where('email', $user->email)->with('customer')->first();
                return response()->json([
                    'message' => 'Otp Send to your mobile',
                    ///   'user' => $user_out,
                    //   'token' => $token,
                    'status' => '1'
                ]);
            } else {
                return response()->json([
                    'message' => 'This account not exist',
                    'status' => '0'
                ]);
            }
        }
    }

    public function register(Request $request)
    {

        $customer = Customer::with('user')->whereHas('user', function ($query) use ($request) {
            $query->where('email', $request->email);
            $query->where('is_admin', false);
        })
            ->where('mobile', $request->mobile)
            ->where('referrel_code', $request->referrel_code)
            ->first();


        $request->validate([
            'name'          => 'required',
            'email'         => ['required', 'email', Rule::unique('users', 'email')->ignore(optional($customer->user)->id)],
            'referrel_code' => ['required', Rule::unique('customers', 'referrel_code')->ignore(optional($customer)->id)],
            'mobile'        => ['required', Rule::unique('customers', 'mobile')->ignore(optional($customer)->id)],
            'password'      => 'required',
        ]);

        // Check if the customer exists
        if (!$customer) {
            return response()->json([
                'message' => 'Invalid referral code. Registration failed.',
                'status'  => '0',
            ], 400);
        }

        // Check if the customer has a user
        if (!$customer->user) {
            return response()->json([
                'message' => 'User does not exist.',
                'status'  => '0',
            ], 400);
        }

        // Hash the password
        $input = $request->all();
        $input['password'] = isset($input['password']) ? $input['password'] : '123456';
        $input['password'] = Hash::make($input['password']);

        // Update user details
        $customer->user->update([
            'name'     => $input['name'],
            'email'    => $input['email'],
            'password' => $input['password'],
        ]);

        // Assign role to user
        $customer->user->assignRole('customer');

        // Update or create the customer record
        $customer = Customer::updateOrCreate(
            ['user_id' => $customer->user->id],
            [
                'mobile'        => $input['mobile'],
                'password'      => $input['password'],
                'referrel_code' => $request->referrel_code,
                'otp'           => OtpHelper::getOtp(),
            ]
        );

        // Fetch the updated user with customer relation
        $user_out = User::with('customer')->where('email', $request->email)->first();

        return response()->json([
            'message' => 'Your account has been registered successfully. Please verify the account using OTP.',
            'user'    => $user_out,
            'status'  => '1',
        ]);
    }


    public function resendOtp(Request $request)
    {
        $request->validate([
            'username' => 'required',

        ]);
        $request->validate([
            'username' => 'required',
        ]);
        $user_count = User::where('email', $request->username)->count();
        if ($user_count == '1') {
            $user = User::where('email', $request->username)->first();
            //   $token = $user->createToken('mykuri-app-token')->plainTextToken;
            //$fourDigitRandomNumber = rand(1000, 9999);
            //  $fourDigitRandomNumber = '4345';
            $customer = Customer::where('user_id', $user->id)->first();
            $customer->otp =  OtpHelper::getOtp();
            $customer->is_verified =  '0';
            $customer->update();
            $user_out = User::where('email', $user->email)->with('customer')->first();
            return response()->json([
                'message' => 'Please Verify the Account using Otp',
                //  'user' => $user_out,
                //  //     'token' => $token,
                'status' => '1'
            ]);
        } else {
            $customer_count = Customer::where('mobile', $request->username)->count();
            if ($customer_count == '1') {
                $customer = Customer::where('mobile', $request->username)->first();
                $user = User::where('id', $customer->user_id)->first();
                //    $token = $user->createToken('mykuri-app-token')->plainTextToken;
                //$fourDigitRandomNumber = rand(1000, 9999);
                $customer = Customer::where('user_id', $user->id)->first();
                $customer->otp =  OtpHelper::getOtp();
                $customer->is_verified =  '0';
                $customer->update();
                $user_out = User::where('email', $user->email)->with('customer')->first();
                return response()->json([
                    'message' => 'Otp Send to your mobile',
                    ///   'user' => $user_out,
                    //   'token' => $token,
                    'status' => '1'
                ]);
            } else {
                return response()->json([
                    'message' => 'This account not exist',
                    'status' => '0'
                ]);
            }
        }
    }
    public function otp(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'otp' => 'required|numeric',
        ]);
        $input =  $request->all();
        $user = User::where('email', $input['username']);
        $count = $user->count();
        if ($count == 1) {
            $user_data =  $user->first();
            $customer = Customer::where('user_id', $user_data->id)->where('otp', $input['otp'])->first();
            if ($customer != "") {
                $input['is_verified'] = 1;
                $customer->update($input);
                $token = $user_data->createToken('mykuri-app-token')->plainTextToken;
                return response()->json([
                    'message' => 'Your account has been verified.',
                    'token' => $token,
                    'status' => '1'
                ]);
            } else {
                return response()->json([
                    'message' => 'Invalid Otp',
                    'status' => '0'
                ]);
            }
        } else {
            $customer = Customer::where('mobile', $input['username'])->where('otp', $input['otp'])->first();
            if ($customer != "") {
                $input['is_verified'] = 1;
                $customer->update($input);
                $user = User::where('id', $customer->user_id)->first();
                $token = $user->createToken('mykuri-app-token')->plainTextToken;
                return response()->json([
                    'message' => 'Your account has been verified.',
                    'token' => $token,
                    'status' => '1'
                ]);
            } else {
                return response()->json([
                    'message' => 'Invalid Otp',
                    'status' => '0'
                ]);
            }
        }
    }

    public function forgotOtp(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'otp' => 'required|numeric',
        ]);
        $input =  $request->all();
        $user = User::where('email', $input['username']);
        $count = $user->count();
        if ($count == 1) {
            $user_data =  $user->first();
            $customer = Customer::where('user_id', $user_data->id)->where('otp', $input['otp'])->first();
            if ($customer != "") {
                $input['is_verified'] = 1;
                $customer->update($input);
                $token = $user_data->createToken('mykuri-app-token')->plainTextToken;
                return response()->json([
                    'message' => 'Your account has been verified.',
                    'token' => $token,
                    'status' => '1'
                ]);
            } else {
                return response()->json([
                    'message' => 'Invalid Otp',
                    'status' => '0'
                ]);
            }
        } else {
            $customer = Customer::where('mobile', $input['username'])->where('otp', $input['otp'])->first();
            if ($customer != "") {
                $input['is_verified'] = 1;
                $customer->update($input);
                $user = User::where('id', $customer->user_id)->first();
                $token = $user->createToken('mykuri-app-token')->plainTextToken;
                return response()->json([
                    'message' => 'Your account has been verified.',
                    'token' => $token,
                    'status' => '1'
                ]);
            } else {
                return response()->json([
                    'message' => 'Invalid Otp',
                    'status' => '0'
                ]);
            }
        }
    }

    public function loginNotVerifiedOtp(Request $request)
    {
        $request->validate([
            'otp' => 'required|numeric',
        ]);
        $input = $request->all();
        $input['is_verified'] = '1';
        $id = auth()->user()->id;
        $customer = Customer::where('user_id', $id)->first();
        if ($input['otp'] == $customer->otp) {
            $customer->update($input);
            return response()->json([
                'message' => 'Your account has been verified.',
                'status' => '1'
            ]);
        } else {
            return response()->json([
                'message' => 'Invalid Otp',
                'status' => '0'
            ]);
        }
    }
    public function changePassword(Request $request)
    {
        $request->validate([
            'password' => 'required',
            'cpassword' => 'required',
        ]);
        $input = $request->all();
        $id = auth()->user()->id;
        $customer = Customer::where('user_id', $id)->first();
        $user = User::where('id', $id)->first();
        if ($input['password'] == $input['cpassword']) {
            $input['password'] =  (isset($input['password']) && $input['password']) ? $input['password'] : '123456';
            $input['password'] = Hash::make($input['password']);
            $customer->update($input);
            $user->update($input);
            $logout = Auth::user()->tokens->each(function ($token, $key) {
                $token->delete();
            });
            return response()->json([
                'message' => 'Password changed successfully',
                'status' => '1'
            ]);
        } else {
            return response()->json([
                'message' => 'Password and confirm Password should be same',
                'status' => '0'
            ]);
        }
    }

    public function logout()
    {
        $logout = Auth::user()->tokens->each(function ($token, $key) {
            $token->delete();
        });

        return response()->json([
            'message' => 'Successfully Logout',
            'status' => '1'
        ]);
    }
}
