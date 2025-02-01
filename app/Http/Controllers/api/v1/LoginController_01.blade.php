<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Customer;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Hash;
use Illuminate\Support\Arr;
use DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use App\Helpers\OtpHelper;

class LoginController extends Controller
{
    //
    public function login(Request $request)
    {

        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $user_count = Customer::where('mobile', $request->username)->count();

        if (Auth::attempt(array('email' => $request->username, 'password' => $request->password))) {

            $user = User::where('email', $request->username)->first();
            $token = $user->createToken('mykuri-app-token')->plainTextToken;
      
            $user_out = User::where('email', $request->username)->with('customer')->first();
          
            if ($user_out->customer->is_verified == '1') {
              
                return response([
                    'status' => '1',
                    'user' => $user_out,
                    'token' => $token,
                    'message' => 'Login Successful',
                ]);
            } else {
                  
               // $fourDigitRandomNumber = '4345';
                $customer = Customer::where('user_id', $user->id)->first();
                $customer->otp =  OtpHelper::getOtp();
            
                $customer->is_verified =  '0';
                $customer->update();
                return response([
                    'status' => '1',
                    'user' => $user_out,
                    'token' => $token,
                    'message' => 'This user is not verified.Otp is send to your mobile',
                ]);
            }
        } elseif ($user_count == '1') {
        
            $customer = Customer::where('mobile', $request->username)->first();
         
            if (!$customer || !Hash::check($request->password, $customer->password)) {
                return response([
                    'status' => '0',
                    'message' => 'Wrong Credentials',
                ]);
            } else {
                $user = User::where('id', $customer->user_id)->first();
                $token = $user->createToken('mykuri-app-token')->plainTextToken;
                $user_out = User::where('email', $user->email)->with('customer')->first();
                if ($user_out->customer->is_verified == '1') {
                    return response([
                        'status' => '1',
                        'user' => $user_out,
                        'token' => $token,
                        'message' => 'Login Successful',
                    ]);
                } else {
               // $fourDigitRandomNumber = '4345';
                $customer = Customer::where('user_id', $user->id)->first();
                $customer->otp =  OtpHelper::getOtp();
                $customer->is_verified =  '0';
                $customer->update();
                    return response([
                        'status' => '1',
                        'user' => $user_out,
                        'token' => $token,
                        'message' => 'This user is not verified',
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

    public function verifyReferalCode(Request $request){
        $request->validate([
            'referrel_code' => 'required',
        ]);

        $customer = Customer::where('referrel_code', $request->referrel_code);
        $customer_count = $customer->count();
        if ($customer_count == '1') {
           $customer_data=$customer->first();
           if($customer_data->is_verified=="0")
           {
           //$fourDigitRandomNumber = rand(1000, 9999);
          // $fourDigitRandomNumber = '4345';
                $customer_data->otp =  OtpHelper::getOtp();
                $customer_data->is_verified =  '1';
                $customer_data->update();
            return response()->json([
                'message' => 'User is verified successfully.Please Login to use App',
                'status' => '1'
            ]);
           }  else {
            return response()->json([
                'message' => 'This Refferal Code is already verified.Try Login',
                'status' => '0'
            ]);
           }

        }
        else {
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
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users,email',
            'referrel_code'=>'required|unique:customers,referrel_code',
            'mobile' => 'required|unique:customers,mobile',
            'password' => 'required',
        ]);
        $input = $request->all();
        //
        $input['password'] =  (isset($input['password']) && $input['password']) ? $input['password'] : '123456';
        // User Info
        $input['password'] = Hash::make($input['password']);
        $user = User::create([
            'name'     => $input['name'],
            'email'    => $input['email'],
            'password' => $input['password']
        ]);
        $user->assignRole('customer');
      //  $fourDigitRandomNumber = rand(1000, 9999);
      //  $fourDigitRandomNumber = '4345';
        $customer = Customer::create([
            'user_id'   => $user->id,
            'mobile'     => $input['mobile'],
            'password' => $input['password'],
            'referrel_code'     => $input['referrel_code'],
            'otp' => OtpHelper::getOtp(),
        ]);
       // $token = $user->createToken('mykuri-app-token')->plainTextToken;
        $user_out = User::with('customer')->where('email', $request->email)->first();
        return response()->json([
            'message' => 'Your account has been registered.Please Verify the Account using Otp',
            'user' => $user_out,
           // 'token' => $token,
            'status' => '1'
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
        $user=User::where('email',$input['username']);
        $count =$user->count();
        if($count==1)
        {
         $user_data =  $user->first();
         $customer =Customer::where('user_id', $user_data->id)->where('otp', $input['otp'])->first();
         if($customer!="")
          {
            $input['is_verified']=1;
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
            $customer =Customer::where('mobile', $input['username'])->where('otp', $input['otp'])->first();
         if($customer!="")
          {
            $input['is_verified']=1;
            $customer->update($input);
            $user=User::where('id',$customer->user_id)->first();
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
        $user=User::where('email',$input['username']);
        $count =$user->count();
        if($count==1)
        {
         $user_data =  $user->first();
         $customer =Customer::where('user_id', $user_data->id)->where('otp', $input['otp'])->first();
         if($customer!="")
          {
            $input['is_verified']=1;
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
            $customer =Customer::where('mobile', $input['username'])->where('otp', $input['otp'])->first();
         if($customer!="")
          {
            $input['is_verified']=1;
            $customer->update($input);
            $user=User::where('id',$customer->user_id)->first();
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
