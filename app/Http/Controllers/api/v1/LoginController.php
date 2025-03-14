<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Customer;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\UserSubscription;
use Illuminate\Support\Arr;
use DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use App\Helpers\OtpHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Exception\MessagingException;



class LoginController extends Controller
{

    public function login(Request $request)
    {
        try {
            $request->validate([
                'mobile' => ['required'],
                'password' => ['required'],
            ]);

            $customer = Customer::where('mobile', $request->mobile)->first();


            if (!$customer) {
                return response()->json([
                    'status' => '0',
                    'message' => 'Please enter a valid mobile no',
                ], 404);
            }

            if (!$customer->password) {
                return response()->json([
                    'status' => '0',
                    'message' => 'Please verify your referral code before setting a password.',
                ], 403);
            }

            if (!Hash::check($request->password, $customer->password)) {
                return response()->json([
                    'status' => '0',
                    'message' => 'Wrong Credentials',
                ], 401);
            }

            $user = User::where('is_admin', false)
                ->findOrFail($customer->user_id);

            if (!$user) {
                return response()->json([
                    'status' => '0',
                    'message' => 'User not found or not authenticated.',
                ], 403);
            }

            $token = $user->createToken('mykuri-app-token')->plainTextToken;

            $customer->is_verified = '1';
            $customer->save();

            return response()->json([
                'status' => '1',
                'user' => $user,
                'token' => $token,
                'accept_tc' => $customer->accept_tc,
                'message' => 'Login Successful',
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => '0',
                'message' => 'An error occurred during login. Please try again later.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function forgotPassword(Request $request)
    {
        try {
            $request->validate([
                'mobile' => 'required',
            ]);

            $user = User::where('mobile', $request->mobile)
                ->where('is_admin', false)
                ->first();

            if (!$user) {
                return response()->json([
                    'message' => 'Please enter a valid mobile no.',
                    'status'  => '0',
                ], 404);
            }

            $customer = Customer::where('user_id', $user->id)
                ->where('mobile', $request->mobile)
                ->first();

            if (!$customer) {
                return response()->json([
                    'message' => 'This account does not exist',
                    'status' => '0'
                ], 404);
            }

            if($customer->is_verified == false) {
                return response()->json([
                    'message' => 'Please verify your account first.',
                    'status' => '0'
                ], 404);
            }


            $otp = OtpHelper::getOtp();
            $customer->otp = $otp;
            $customer->is_verified = 0;
            $customer->save();

            OtpHelper::sendOtpSms($request->mobile, $otp);

            return response()->json([
                'message' => 'OTP sent to your mobile',
                'status' => '1'
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'An error occurred. Please try again later.',
                'status' => '0',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyReferalCode(Request $request)
    {
        $request->validate([
            'referral_code' => ['required'],
        ]);

        $customer = Customer::with('user')
            ->whereHas('user', function ($query) {
                $query->where('is_admin', false);
            })
            ->where('referrel_code', $request->referral_code)
            ->first();

        if (!$customer || !$customer->user) {
            return response()->json([
                'message' => 'Invalid referral code.',
                'status'  => '0',
            ], 400);
        }

        if($customer->is_verified == true && $customer->password) {
            return response()->json([
                'message' => 'Customer referrel code already verified, Please login',
                'status'  => '0',
            ], 400);
        }

        $otp = OtpHelper::getOtp();
        $customer->otp = $otp;
        $customer->is_verified = 1;
        $customer->save();
        
        OtpHelper::sendOtpSms($customer->mobile, $otp);

        return response()->json([
            'message' => 'Referral code validated. OTP sent to your mobile.',
            'status'  => '1',
            'otp' => $customer->otp
        ]);
    }


    public function createPassword(Request $request)
    {
        $request->validate([
            'password'  => 'required|string|min:6',
            'cpassword' => 'required|same:password',
        ]);

        $authUser = auth()->user();

        if (!$authUser) {
            return response()->json([
                'message' => 'Unauthorized. Please login again.',
                'status'  => '0',
            ], 401);
        }

        $user = User::findOrFail($authUser->id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
                'status'  => '0',
            ], 404);
        }

        // Check if user already has a password
        if ($user->password) {
            return response()->json([
                'message' => 'Password is already set. Use reset password if needed.',
                'status'  => '0',
            ], 400);
        }

        // Find the linked customer and ensure OTP is verified
        $customer = Customer::where('user_id', $user->id)->first();
        $customer->password = Hash::make($request->password);
        $customer->save();

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'message' => 'Password created successfully.',
            'status'  => '1',
        ]);
    }



    public function resendOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required',
        ]);

        $user = User::where('mobile', $request->mobile)
            ->where('is_admin', false)
            ->first();

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
                'status'  => '0',
            ], 404);
        }

        $customer = Customer::where('mobile', $request->mobile)
            ->where('user_id', $user->id)
            ->first();

        if (!$customer) {
            return response()->json([
                'message' => 'This account does not exist.',
                'status'  => '0'
            ]);
        }

        $otp = OtpHelper::getOtp();

        $customer->otp = $otp;
        $customer->is_verified = 0;
        $customer->save();

        OtpHelper::sendOtpSms($request->mobile, $otp);

        return response()->json([
            'message' => 'OTP has been resent to your mobile.',
            'status'  => '1'
        ]);
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'referral_code' => 'required',
            'otp' => 'required|numeric',
        ]);

        $customer = Customer::where('referrel_code', $request->referral_code)
            ->where('is_verified', true)
            ->where('otp', $request->otp)
            ->first();

        // If no customer is found, return an error response
        if (!$customer) {
            return response()->json([
                'message' => 'Invalid OTP or referral code.',
                'status'  => '0',
            ], 404);
        }

        if ($customer->is_verified == false) {
            $customer->otp = $request->otp;
            $customer->is_verified = 1;
            $customer->update();
        }

        if ($customer->otp == $request->otp && $customer->is_verified == true) {
            $user = User::find($customer->user_id);

            if (!$user) {
                return response()->json([
                    'message' => 'User not found.',
                    'status'  => '0',
                ], 404);
            }

            $token = $user->createToken('mykuri-app-token')->plainTextToken;

            return response()->json([
                'message' => 'Your account has been verified.',
                'token'   => $token,
                'status'  => '1',
            ]);
        }

        return response()->json([
            'message' => 'Invalid OTP.',
            'status'  => '0',
        ]);
    }


    public function forgotOtp(Request $request)
    {
        $request->validate([
            'mobile' => 'required',
            'otp' => 'required|numeric',
        ]);

        $input =  $request->all();
        $user = User::where('mobile', $input['mobile'])
            ->where('is_admin', false);

        if ($user->count() == 1) {
            $userData =  $user->first();
            $customer = Customer::where('user_id', $userData->id)->where('otp', $input['otp'])->first();
            if (!empty($customer)) {
                $input['is_verified'] = 1;
                $customer->update($input);
                $token = $userData->createToken('mykuri-app-token')->plainTextToken;

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
            $customer = Customer::where('mobile', $input['mobile'])->where('otp', $input['otp'])->first();
            if (!empty($customer)) {
                $input['is_verified'] = 1;
                $customer->update($input);
                $user = User::where('is_admin', false)
                    ->findOrFail($customer->user_id);
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
        $input['is_verified'] = 1;
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
        $user = User::where('is_admin', false)
            ->findOrFail($id);

        if (!$user) {
            return response()->json([
                'message' => 'User not found.',
                'status'  => '0',
            ], 404);
        }

        $customer = Customer::where('user_id', $user->id)->first();

        if ($input['password'] == $input['cpassword']) {
            $input['password'] =  (isset($input['password']) && $input['password']) ? $input['password'] : '123456';
            $input['password'] = Hash::make($input['password']);
            $customer->update($input);
            $user->update($input);
            Auth::user()->tokens->each(function ($token, $key) {
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

    public function acceptTermsAndConditions()
    {
        $authUserId = auth()->user()->id;

        if (Auth::check()) {

            $customer = Customer::where('user_id', $authUserId)->first();
            $customer->accept_tc = true;
            $customer->update();

            return response()->json([
                'status' => '1',
                'message' => 'Terms and Conditions successfully accepted',
            ], 200);
        } else {
            return response()->json([
                'status' => '0',
                'message' => 'User is not logged in'
            ], 401);
        }
    }

    public function logout()
    {
        Auth::user()->tokens->each(function ($token, $key) {
            $token->delete();
        });

        return response()->json([
            'message' => 'Successfully Logout',
            'status' => '1'
        ]);
    }
}
