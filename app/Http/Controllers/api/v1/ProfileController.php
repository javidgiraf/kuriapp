<?php

namespace App\Http\Controllers\api\v1;

use App\Helpers\MachineHelper;
use App\Helpers\UniqueHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Address;
use App\Models\Country;
use App\Models\State;
use App\Models\Customer;
use App\Models\User;
use App\Models\Nominee;
use App\Models\District;
use App\Models\UserSubscription;
use App\Models\Deposit;
use App\Models\DepositPeriod;
use App\Models\GoldRate;
use App\Models\GoldDeposit;
use App\Models\Setting;
use Carbon\Carbon;
use App\Helpers;
use App\Models\RazorpayTransaction;
use Illuminate\Support\Arr;
use Razorpay\Api\Api;
use App\Models\TransactionDetail;
use App;
use App\Models\SchemeSetting;
use App\Models\SchemeType;
use DateTime;
use Illuminate\Support\Str;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
  //



  public function profile()
  {
    $id = auth()->user()->id;
    $user = User::with('customer')->with('nominee')->whereId($id)->first();
    $address = Address::with('country')->with('state')->with('district')->where('user_id', $id)->first();
    return response()->json([
      'user' => $user,
      'address' => $address,
      'status' => '1'
    ]);
  }

  public function profileUpdate(Request $request)
  {
    $id = auth()->user()->id;

    // Fetch the customer ID associated with the user
    $customer = Customer::whereUserId($id)->first();
    if (!$customer) {
      return response()->json(['message' => 'Customer not found.', 'status' => '0'], 404);
    }

    $customerId = $customer->id;

    // Validation rules
    $validationRules = [
      'name' => 'required|string|max:255',
      'email' => [
        'required',
        'email',
        Rule::unique('users', 'email')->ignore($id),
      ],
      'mobile' => [
        'required',
        'numeric',
        Rule::unique('customers', 'mobile')->ignore($id, 'user_id'),
        'digits_between:10,15',
        'regex:/^[6-9]\d{9}$/',
      ],
      'aadhar_number' => [
        'nullable',
        'numeric',
        'regex:/^\d{12}$/',
        Rule::unique('customers', 'aadhar_number')->ignore($customerId),
      ],
      'pancard_no' => [
        'nullable',
        'string',
        'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
        Rule::unique('customers', 'pancard_no')->ignore($customerId),
      ],
      'address' => 'required|string|max:500',
      'country_id' => 'required|integer|exists:countries,id',
      'state_id' => 'required|integer|exists:states,id',
      'district_id' => 'required|integer|exists:districts,id',
      'pincode' => 'required|numeric|digits:6',
      'nominee_name' => 'required|string|max:255',
      'nominee_relationship' => 'required|string|max:50',
      'nominee_phone' => 'required|numeric|digits_between:10,15|regex:/^[6-9]\d{9}$/',
    ];

    // Additional validation: Either aadhar_number or pancard_no must be provided
    $validationRules['aadhar_number'] = [
      'required_without:pancard_no',
      'nullable',
      'numeric',
      'regex:/^\d{12}$/',
      Rule::unique('customers', 'aadhar_number')->ignore($customerId),
    ];
    $validationRules['pancard_no'] = [
      'required_without:aadhar_number',
      'nullable',
      'string',
      'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
      Rule::unique('customers', 'pancard_no')->ignore($customerId),
    ];

    $validatedData = $request->validate($validationRules);

    // Update user details
    $userUpdateData = [
      'name' => $request->name,
      'email' => $request->email,
    ];

    if ($request->filled('password')) {
      $userUpdateData['password'] = Hash::make($request->password);
    }

    User::where('id', $id)->update($userUpdateData);

    // Update customer details
    $customerUpdateData = [
      'mobile' => $request->mobile,
      'aadhar_number' => $request->aadhar_number,
      'pancard_no' => $request->pancard_no,
    ];

    Customer::where('user_id', $id)->update($customerUpdateData);

    // Update or create address
    Address::updateOrCreate(
      ['user_id' => $id],
      [
        'address' => $request->address,
        'country_id' => $request->country_id,
        'state_id' => $request->state_id,
        'district_id' => $request->district_id,
        'pincode' => $request->pincode,
      ]
    );

    // Update or create nominee details
    Nominee::updateOrCreate(
      ['user_id' => $id],
      [
        'name' => $request->nominee_name,
        'relationship' => $request->nominee_relationship,
        'phone' => $request->nominee_phone,
      ]
    );

    return response()->json([
      'message' => 'Profile updated successfully.',
      'status' => '1',
    ]);
  }



  public function get_all_countries()
  {
    $countries = Country::all();
    return response()->json([
      'countries' => $countries,
      'status' => '1'
    ]);
  }
  public function get_states_by_country($id)
  {
    $states = State::where('country_id', $id)->get();
    return response()->json([
      'states' => $states,
      'status' => '1'
    ]);
  }
  public function get_districts_by_state($id)
  {
    $districts = District::where('state_id', $id)->get();
    return response()->json([
      'districts' => $districts,
      'status' => '1'
    ]);
  }


  public function userSubscriptions()
  {
    $id = auth()->user()->id;

    $user_subscriptions = UserSubscription::with(['deposits.deposit_periods', 'scheme.schemeType', 'scheme.schemeSetting'])
      ->where('user_id', $id)
      ->where('is_closed', false)
      ->latest()
      ->get()
      ->map(function ($subscription) {
        $scheme = $subscription->scheme;
        $schemeType = $scheme->schemeType;
        $schemeSetting = $subscription->schemeSetting;

        $startDate = Carbon::parse($subscription->start_date);
        $endDate = Carbon::parse($subscription->end_date);

        $currentDate = now();
        $duration = $subscription->scheme->schemeSetting->due_duration;
        $schemeType = SchemeType::find($subscription->scheme->scheme_type_id);
        $flexibility_duration = $schemeType ? $schemeType->flexibility_duration : 0;
        $holdDateFlexible = $startDate->copy()->addMonths($flexibility_duration);

        $monthKey = $currentDate->format('Y-m');
        $existingPayments = DepositPeriod::whereHas('deposit', function ($query) use ($subscription) {
          $query->where('subscription_id', $subscription->id)
            ->where('status', true);
        })
          ->whereRaw("DATE_FORMAT(due_date, '%Y-%m') = ?", [$monthKey])
          ->where('status', true)
          ->exists();

        // Default is_flexible value
        $isFlexible = true;


        if ($schemeType->id == SchemeType::FIXED_PLAN) {
          $isFlexible = false;
        }

        if (
          $currentDate->greaterThanOrEqualTo($holdDateFlexible) &&
          $subscription->scheme->scheme_type_id != SchemeType::FIXED_PLAN
        ) {
          $isFlexible = false;
        }

        $havePaidStatus = false;

        if ($schemeType->id == SchemeType::FIXED_PLAN && $existingPayments) {
          $havePaidStatus = true;
        }

        // Check if a deposit exists for the current month
        $currentMonth = now()->format('Y-m');
        $depositExistsThisMonth = $subscription->deposits->contains(function ($deposit) use ($currentMonth) {
          return Carbon::parse($deposit->paid_at)->format('Y-m') === $currentMonth && $deposit->status == 1;
        });

        $dueDate = now();

        // Calculate due date and check due status
        if ($schemeType->id == SchemeType::FIXED_PLAN) {
          $dueDate = Carbon::now()->startOfMonth()->addDays($duration);
        }

        $flexibilityDuration = $schemeType->flexibility_duration ?? 6;
        $endSixMonthPeriod = (clone $startDate)->addMonths($flexibilityDuration);

        if ($currentDate->greaterThanOrEqualTo($endSixMonthPeriod) && $schemeType->id !== SchemeType::FIXED_PLAN) {
          $dueDate = (now()->format('d') > 15) ?
            Carbon::now()->addMonths(1)->startOfMonth()->addDays($duration)
            : Carbon::now()->startOfMonth()->addDays($duration);
        }
        $isDue = $dueDate && !$depositExistsThisMonth && now()->greaterThanOrEqualTo($dueDate);

        // Check hold status
        $holdStatus = $subscription->status != 1;
        $claimDate = Carbon::parse($subscription->claim_date)->format('Y-m-d');
        $claimStatus = ($subscription->claim_status == true) ? true : false;
        return [
          'id' => $subscription->id,
          'user_id' => $subscription->user_id,
          'scheme_id' => $subscription->scheme_id,
          'starting_date' => Carbon::parse($subscription->start_date)->format('d-m-Y'),
          'ending_date' => Carbon::parse($subscription->end_date)->format('d-m-Y'),
          'join_date' => Carbon::parse($subscription->created_at)->format('d-m-Y'),
          'subscribe_amount' => $subscription->subscribe_amount ?? 0,
          'is_closed' => $subscription->is_closed,
          'status' => $subscription->status,
          'is_flexible' => $isFlexible,
          'title' => $scheme->{'title_' . app()->getLocale()},
          'date' => $dueDate ? $dueDate->format('Y-m-d') : null,
          'is_due' => $isDue,
          'hold_status' => $holdStatus,
          'claim_date' => $claimDate,
          'claim_status' => $claimStatus,
          'have_paid' => $havePaidStatus,
          'payment_terms' => $scheme->{'payment_terms_' . app()->getLocale()},
        ];
      });

    return response()->json([
      'user_subscriptions' => $user_subscriptions,
      'status' => '1'
    ], 200, [], JSON_UNESCAPED_UNICODE);
  }





  function generateDates($start_date_str, $end_date_str)
  {
    $start_date = Carbon::parse($start_date_str);
    $end_date = Carbon::parse($end_date_str);

    $current_date = $start_date;
    $dates_list = [];

    while ($current_date <= $end_date) {
      $dates_list[] = $current_date->format('d-m-Y');
      if ($current_date->format('m-Y') == now()->format('m-Y')) {
        break;
      }

      $current_date->addMonth();
    }

    return $dates_list;
  }



  public function plan_duration($scheme_id)
  {
    $id = auth()->user()->id;
    $user_subscriptions = UserSubscription::with('scheme')
      ->where('user_id', $id)
      ->where('scheme_id', $scheme_id)
      ->latest()
      ->get();

    $rs_dates = [];
    collect($user_subscriptions)->map(function ($user_subscription) {
      $user_subscription_deposits =  Deposit::where('subscription_id', $user_subscription->id)->get();

      $start_date_str = $user_subscription->start_date;
      $end_date_str = $user_subscription->end_date;

      $result_dates = $this->generateDates($start_date_str, $end_date_str);
      foreach ($result_dates as &$d) {
        $rs_dates[] = [
          'date' => $d,
          'amount' => $user_subscription->scheme->schedule_amount,
          'is_due' => 0,
          'status' => '0',

        ];
      }
      if ($user_subscription_deposits != "") {
        $deposit_periods = [];
        foreach ($user_subscription_deposits as $dp) {
          $deposit_periods[] = $dp->deposit_periods
            ->where('status', 1)
            ->toarray();
        }

        $deposit_dues = [];
        foreach ($user_subscription_deposits as $dp) {
          $deposit_dues[] = $dp->deposit_periods
            ->where('is_due', 1)
            ->toarray();
        }

        $flattenedArray = array_merge_recursive(...$deposit_periods);
        $flattenedduesArray = array_merge_recursive(...$deposit_dues);

        $items = [];
        foreach ($flattenedArray as &$item) {
          $items[] = [

            'due_date' =>
            Carbon::parse($item['due_date'])->format('d-m-Y'),
            'is_due' => $item['is_due'],
            'status' => $item['status'],
          ];
        }

        $dues = [];
        foreach ($flattenedduesArray as &$due) {
          $dues[] = [

            'due_date' =>
            Carbon::parse($due['due_date'])->format('d-m-Y'),
            'is_due' => $due['is_due'],
            'status' => $due['status'],
          ];
        }

        foreach ($rs_dates as &$item1) {
          $item1['status'] = 0;
          $item1['is_due'] = 0;

          foreach ($items as $item2) {
            if ($item1['date'] === $item2['due_date']) {
              if ($item2['status'] === 1) {
                $item1['status'] = 1;
              }
              break;
            }
          }
          foreach ($dues as $item2) {

            if ($item1['date'] === $item2['due_date']) {
              if ($item2['is_due'] === 1) {
                $item1['is_due'] = 1;
              }
              break;
            }
          }
        }
      }
    });

    return response()->json([
      'result_dates' => $rs_dates,
      'status' => '1'
    ]);
  }


  public function current_plan_history($scheme_id)
  {
    $id = auth()->user()->id;

    $user_subscription = UserSubscription::with(['deposits.deposit_periods', 'scheme.schemeSetting', 'schemeSetting', 'scheme.schemeType'])
      ->where('user_id', $id)
      ->where('scheme_id', $scheme_id)
      ->findOrFail(request('sub_id'));

    if ($user_subscription) {
      $user_subscription_deposits = Deposit::where('subscription_id', $user_subscription->id)
        ->where('status', '1')
        ->get();

      $deposit_periods = [];
      $sum = 0;

      foreach ($user_subscription_deposits as $dp) {
        $deposit_periods[] = $dp->paid_at;

        if ($dp->status == 1) {
          $sum += $dp->final_amount;
        }
      }

      $scheme = $user_subscription->scheme;
      $schemeType = $scheme->schemeType;
      $startDate = Carbon::parse($user_subscription->start_date);
      $endDate = Carbon::parse($user_subscription->end_date);
      $currentDate = now();
      $flexibilityDuration = $schemeType->flexibility_duration ?? 6; // First 6 months
      $endSixMonthPeriod = (clone $startDate)->addMonths($flexibilityDuration);
      $balance_amount = 0;

      if ($currentDate->greaterThanOrEqualTo($endSixMonthPeriod) && $schemeType->id !== SchemeType::FIXED_PLAN) {
        $monthsCount = 0;
        $start = (clone $startDate)->modify('+6 months');
        $end = (new DateTime($user_subscription->end_date))->modify('first day of next month');

        if ($start < $end) {
          while ($start < $end) {
            $start->modify('first day of next month');
            $monthsCount++;
          }
        }

        $totalFlexibleSchemeAmount = Deposit::where('subscription_id', $user_subscription->id)
          ->where('paid_at', '>=', $endSixMonthPeriod->format('Y-m-d'))
          ->where('paid_at', '<=', $endDate->format('Y-m-d'))
          ->where('total_scheme_amount', $user_subscription->subscribe_amount)
          ->where('status', '1')
          ->sum('final_amount');

        $expectedAmount = $user_subscription->subscribe_amount * $monthsCount;

        $balance_amount = max(0, $expectedAmount - ($totalFlexibleSchemeAmount ?? 0));
      }

      if ($schemeType->id == SchemeType::FIXED_PLAN) {
        $start = new DateTime($user_subscription->start_date);
        $end = new DateTime($user_subscription->end_date);

        $end->modify('first day of next month');
        $monthsCount = 0;

        while ($start < $end) {
          $start->modify('first day of next month');
          $monthsCount++;
        }

        $expectedAmount = $user_subscription->subscribe_amount * $monthsCount;

        // Fix negative balance issue
        $balance_amount = max(0, $expectedAmount - ($sum ?? 0));
      }


      $payment_history = [];
      $isGold = $schemeType && strtolower($schemeType->shortcode) === 'gold';
      $goldWeightSum = 0;

      foreach ($user_subscription_deposits as $dp) {
        $paymentData = [
          'scheme_amount' => $dp->final_amount,
          'paid_at' => Carbon::parse($dp->paid_at)->format('d-m-Y'),
          'status' => $dp->status,
        ];

        if ($isGold) {
          $goldDeposit = GoldDeposit::where('deposit_id', $dp->id)->first();
          $paymentData['gold_weight'] = $goldDeposit ? number_format($goldDeposit->gold_weight, 2) : null;
          $goldWeightSum += $paymentData['gold_weight'];
        }

        $payment_history[] = $paymentData;
      }

      $startDate = Carbon::parse($user_subscription->start_date);
      $currentDate = now();

      $monthKey = $currentDate->format('Y-m');
      $existingPayments = DepositPeriod::whereHas('deposit', function ($query) use ($user_subscription) {
        $query->where('subscription_id', $user_subscription->id)
          ->where('status', true);
      })
        ->whereRaw("DATE_FORMAT(due_date, '%Y-%m') = ?", [$monthKey])
        ->where('status', true)
        ->exists();

      $schemeStatus = true;


      if ($schemeType->id == SchemeType::FIXED_PLAN) {
        $schemeStatus = false;
      }

      $duration = $user_subscription->scheme->schemeSetting->due_duration;
      $schemeType = SchemeType::find($user_subscription->scheme->scheme_type_id);
      $flexibility_duration = $schemeType ? $schemeType->flexibility_duration : 0;
      $holdDateFlexible = $startDate->copy()->addMonths($flexibility_duration);

      if (
        $currentDate->greaterThanOrEqualTo($holdDateFlexible) &&
        $user_subscription->scheme->scheme_type_id != SchemeType::FIXED_PLAN
      ) {
        $schemeStatus = false;
      }

      $havePaidStatus = false;

      if ($schemeType->id == SchemeType::FIXED_PLAN && $existingPayments) {
        $havePaidStatus = true;
      }

      if (
        $currentDate->greaterThanOrEqualTo($holdDateFlexible) &&
        $user_subscription->scheme->scheme_type_id != SchemeType::FIXED_PLAN &&
        $existingPayments
      ) {
        $havePaidStatus = true;
      }

      $currentMonth = Carbon::now()->format('Y-m');
      $depositExistsThisMonth = $user_subscription->deposits->contains(function ($deposit) use ($currentMonth) {
        return Carbon::parse($deposit->paid_at)->format('Y-m') === $currentMonth && $deposit->status == 1;
      });

      $currentLang = app()->getLocale();
      $schemeTitle = $user_subscription->scheme->{'title_' . $currentLang};
      $paymentTerms = $user_subscription->scheme->{'payment_terms_' . $currentLang};
      $schemeDescription = $user_subscription->scheme->{'description_' .  $currentLang};
      $termsDescription = $user_subscription->scheme->{'terms_and_conditions_' . $currentLang};
      $closedStatus = ($user_subscription->is_closed == true) ? true : false;
      $claimedStatus =  ($user_subscription->claim_status == true) ? true : false;
      $claimedDate = Carbon::parse($user_subscription->claim_date)->format('d-m-Y');

      return response()->json([
        'scheme_name' => $schemeTitle,
        'is_gold' => $isGold,
        'payment_terms' => $paymentTerms,
        'scheme_description' => $schemeDescription,
        'terms_description' => $termsDescription,
        'starting_date' => $startDate->format('d-m-Y'),
        'ending_date' => Carbon::parse($user_subscription->end_date)->format('d-m-Y'),
        'join_date' => Carbon::parse($user_subscription->created_at)->format('d-m-Y'),
        'total_amount_paid' => $sum,
        'balance_amount' => $balance_amount ?? 0,
        'have_paid' => $havePaidStatus,
        'status' => '1',
        'is_closed' => $closedStatus,
        'claimed_status' => $claimedStatus,
        'claimed_date' => $claimedDate,
        'goldWeight' => number_format($goldWeightSum, 2),
        'goldUnit' => 'g',
        'payment_date' => now()->startOfMonth()->format('Y-m-d'),
        'scheme_status' => $schemeStatus,
        'deposit_periods' => $payment_history,
      ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    return response()->json([
      'message' => 'Subscription not found',
      'status' => '0'
    ], 404, [], JSON_UNESCAPED_UNICODE);
  }


  public function deposit(Request $request, $sub_id)
  {
    $userId = auth()->user()->id;
    $userSubscription = UserSubscription::with([
      'deposits.deposit_periods',
      'scheme.schemeType',
      'scheme.schemeSetting',
    ])
      ->where('user_id', $userId)
      ->where('is_closed', false)
      ->findOrFail($sub_id);

    $minPayableAmount = $userSubscription->scheme->schemeSetting->min_payable_amount;
    $maxPayableAmount = $userSubscription->scheme->schemeSetting->max_payable_amount;

    $request->validate([
      'total_scheme_amount' => "required|numeric|min:{$minPayableAmount}|max:{$maxPayableAmount}",
    ]);

    if (!$userSubscription) {
      return response()->json([
        'success' => false,
        'message' => 'User subscription not found.',
      ], 404);
    }

    $scheme = $userSubscription->scheme;
    $schemeType = $scheme->schemeType;
    $startDate = Carbon::parse($userSubscription->start_date);
    $currentDate = now();
    $flexibilityDuration = $schemeType->flexibility_duration ?? 6; // First 6 months
    $endSixMonthPeriod = (clone $startDate)->addMonths($flexibilityDuration);

    $totalFlexibleSchemeAmount = DepositPeriod::whereHas('deposit', function ($query) use ($userSubscription) {
      $query->where('subscription_id', $userSubscription->id)
        ->where('status', true);
    })
      ->where('due_date', '>=', $startDate->format('Y-m-d'))
      ->where('due_date', '<', $endSixMonthPeriod->format('Y-m-d'))
      ->where('status', true)
      ->sum('scheme_amount');

    // Validate one payment per month after six months
    // if ($currentDate->greaterThan($endSixMonthPeriod)) {
    //   $monthKey = $currentDate->format('Y-m');
    //   $existingPayments = DepositPeriod::whereHas('deposit', function ($query) use ($userSubscription) {
    //     $query->where('subscription_id', $userSubscription->id)
    //       ->where('status', true);
    //   })
    //     ->where('due_date', '>=', $endSixMonthPeriod->format('Y-m-d'))
    //     ->whereRaw("DATE_FORMAT(due_date, '%Y-%m') = ?", [$monthKey])
    //     ->where('status', true)
    //     ->exists();

    //   if (
    //     $existingPayments
    //     && $schemeType->id !== SchemeType::FIXED_PLAN
    //   ) {
    //     return response()->json([
    //       'success' => false,
    //       'message' => "Only one payment is allowed per month after the 6-month period. A payment already exists for " . $currentDate->format('F Y') . ".",
    //     ], 400);
    //   }
    // }

    // if ($currentDate->greaterThan($endSixMonthPeriod) && $schemeType->id !== SchemeType::FIXED_PLAN) {
    //   $allowedAmount = $flexibilityDuration
    //     ? ($totalFlexibleSchemeAmount / $flexibilityDuration)
    //     : 0;

    //   if ($request->total_scheme_amount > round($allowedAmount)) {
    //     return response()->json([
    //       'success' => false,
    //       'message' => "The deposit amount exceeds the allowable limit of " . round($allowedAmount) . " after the 6-month period.",
    //     ], 400);
    //   }
    // }


    // Create the deposit
    $orderId = UniqueHelper::UniqueID();
    $serviceCharge = 0.00;
    $gstCharge = 0.00;
    $totalSchemeAmount = $request->total_scheme_amount;
    $finalAmount = $totalSchemeAmount + $serviceCharge + $gstCharge;

    $deposit = Deposit::create([
      'subscription_id' => $sub_id,
      'order_id' => $orderId,
      'total_scheme_amount' => $totalSchemeAmount,
      'final_amount' => $finalAmount,
      'paid_at' => $currentDate,
    ]);

    // Handle Gold Deposits
    if ($schemeType->shortcode === "Gold") {
      $latestGoldRate = GoldRate::where('status', 1)->latest('date_on')->first();

      if (!$latestGoldRate) {
        return response()->json([
          'success' => false,
          'message' => 'Gold rate information is unavailable.',
        ], 400);
      }

      $goldWeight = $totalSchemeAmount / $latestGoldRate->per_gram;

      GoldDeposit::create([
        'deposit_id' => $deposit->id,
        'gold_weight' => $goldWeight,
        'gold_unit' => 'gram',
        'status' => '1',
      ]);
    }

    // Create Deposit Period
    $dueDuration = $scheme->schemeSetting->due_duration;
    $dueDate = now();

    if ($schemeType->id == SchemeType::FIXED_PLAN) {
      $dueDate = Carbon::now()->startOfMonth()->addDays($dueDuration);
    }

    if ($currentDate->greaterThanOrEqualTo($endSixMonthPeriod) && $schemeType->id !== SchemeType::FIXED_PLAN) {
      $dueDate = (now()->format('d') > 15) ?
        Carbon::now()->addMonths(1)->startOfMonth()->addDays($dueDuration)
        : Carbon::now()->startOfMonth()->addDays($dueDuration);
    }

    // $paymentDate = Carbon::now()->startOfMonth()->addMonth();

    // if ($paymentDate->greaterThan($currentDate)) {
    //   return response()->json([
    //     'success' => '0',
    //     'message' => 'The payment date cannot be after the current date.'
    //   ], 400);
    // }

    DepositPeriod::create([
      'deposit_id' => $deposit->id,
      'due_date' => $dueDate,
      'scheme_amount' => $totalSchemeAmount,
      'is_due' => $currentDate->greaterThanOrEqualTo($dueDate) ? '1' : '0',
    ]);

    // Prepare Razorpay Transaction
    $api = new Api(env('API_KEY'), env('API_SECRET'));
    $amountInPaisa = $finalAmount * 100;
    $order = $api->order->create([
      'receipt' => $orderId,
      'amount' => $amountInPaisa,
      'currency' => 'INR',
    ]);

    RazorpayTransaction::create([
      'deposit_id' => $deposit->id,
      'razorpay_order_id' => $order->id,
    ]);

    // Prepare Response
    $responseData = [
      'api_key' => env('API_KEY'),
      'order_id' => $order->id,
      'amount' => $finalAmount,
      'status' => '1',
    ];

    if ($schemeType->shortcode === "Gold") {
      $responseData['gold_weight'] = number_format($goldWeight, 2);
      $responseData['gold_unit'] = 'gram';
    }

    return response()->json($responseData);
  }




  public function verifySignature(Request $request)
  {
    $input = $request->all();
    $api = new Api(env('API_KEY'), env('API_SECRET'));
    $api->utility->verifyPaymentSignature($input);

    $razor_pay_transaction = RazorpayTransaction::where('razorpay_order_id', $input['razorpay_order_id'])->first();
    $razor_pay_transaction->update([
      'razorpay_payment_id' => $input['razorpay_payment_id'],
      'razorpay_signature' => $input['razorpay_signature'],
      'status' => 1
    ]);

    $deposit = Deposit::where('id', $razor_pay_transaction->deposit_id)->with('deposit_periods')->first();
    foreach ($deposit->deposit_periods as $deposit_period) {
      $dp = DepositPeriod::where('id', $deposit_period->id)->first();
      $dp->update([
        'status' => 1,
      ]);
    }

    $deposit->update([
      'status' => '1',
    ]);

    return response()->json([
      'status' => '1',
      'message' => 'Payment Success',
      'id' => $deposit->id,
    ]);
  }

  public function wallet()
  {
    $id = auth()->user()->id;

    $user_subscriptions = UserSubscription::with('scheme', 'deposits')
      ->where('user_id', $id)
      ->orderBy('created_at', 'desc')
      ->first();

    $schemeType = $user_subscriptions->scheme->scheme_type_id;
    $matureStatus = $user_subscriptions->is_closed;
    $claim_date = Carbon::parse($user_subscriptions->claim_date)->format('d-m-Y');
    $claim_status = $user_subscriptions->claim_status;
    $support_contact = Setting::first()->contact_support;


    $sum = $user_subscriptions->deposits()
      ->where('status', 1)
      ->sum('final_amount');

    $profileUpdated = $this->isProfileComplete();
    $userName = auth()->user()->name;

    $goldWeight = 0;
    if ($schemeType == SchemeType::GOLD_PLAN) {
      $latestGoldRate = GoldRate::select('date_on', 'per_gram')->latest('date_on')->first();
      $goldWeight = $sum / $latestGoldRate->per_gram;
    }

    return response()->json([
      'wallet' => $sum,
      'goldWeight' => number_format($goldWeight, 2),
      'goldUnit' => 'g',
      'status' => '1',
      'claim_date' => $claim_date,
      'claim_status' => $claim_status,
      'matureStatus' => $matureStatus,
      'profileUpdated' => $profileUpdated,
      'userNmae' => $userName,
      'support_contact' => $support_contact
    ]);
  }

  public function my_payments()
  {
    try {
      // Check if user is authenticated
      if (!auth()->check()) {
        return response()->json([
          'message' => 'User not authenticated',
          'status' => '0'
        ], 401);
      }

      $id = auth()->user()->id;

      // Fetch user subscription
      $user_subscription = UserSubscription::with('scheme')
        ->where('user_id', $id)
        ->orderBy('created_at', 'desc')
        ->first();


      // Check if subscription exists
      if (!$user_subscription) {
        return response()->json([
          'message' => 'No subscription found for the user',
          'status' => '0'
        ], 404);
      }

      // **Check if scheme exists**
      if (!$user_subscription->scheme) {
        return response()->json([
          'message' => 'Scheme not found for this subscription',
          'status' => '0'
        ], 404);
      }

      $deposits = [];
      $current_scheme_latest_month = [];

      // Fetch user deposits safely
      $user_subscription_deposits = Deposit::where('subscription_id', $user_subscription->id)->get();
      $start_date_str = $user_subscription->start_date;
      $end_date_str = $user_subscription->end_date;
      $result_dates = $this->generateDates($start_date_str, $end_date_str);

      $rs_dates = [];
      foreach ($result_dates as &$d) {
        $rs_dates[] = [
          'date' => $d,
          'amount' => $user_subscription->subscribe_amount ?? 0,
          'is_due' => 0,
          'status' => '0',
        ];
      }

      if ($user_subscription_deposits->isNotEmpty()) {
        $deposit_periods = [];
        foreach ($user_subscription_deposits as $dp) {
          if (is_iterable($dp->deposit_periods)) {
            $deposit_periods[] = $dp->deposit_periods
              ->where('status', 1)
              ->toArray();
          }
        }

        $flattenedArray = !empty($deposit_periods) ? array_merge(...$deposit_periods) : [];
        $deposit_dues = [];
        foreach ($user_subscription_deposits as $dp) {
          if (is_iterable($dp->deposit_periods)) {
            $deposit_dues[] = $dp->deposit_periods
              ->where('is_due', 1)
              ->toArray();
          }
        }

        $flattenedduesArray = !empty($deposit_dues) ? array_merge(...$deposit_dues) : [];

        $items = [];
        foreach ($flattenedArray as &$item) {
          if (is_array($item)) {
            $items[] = [
              'due_date' => Carbon::parse($item['due_date'] ?? now())->format('d-m-Y'),
              'is_due' => $item['is_due'] ?? 0,
              'status' => $item['status'] ?? 0,
            ];
          }
        }

        $dues = [];
        foreach ($flattenedduesArray as &$due) {
          if (is_array($due)) {
            $dues[] = [
              'due_date' => Carbon::parse($due['due_date'] ?? now())->format('d-m-Y'),
              'is_due' => $due['is_due'] ?? 0,
              'status' => $due['status'] ?? 0,
            ];
          }
        }

        foreach ($rs_dates as &$item1) {
          $item1['status'] = 0;
          $item1['is_due'] = 0;

          foreach ($items as $item2) {
            if ($item1['date'] === $item2['due_date']) {
              if ($item2['status'] === 1) {
                $item1['status'] = 1;
              }
              break;
            }
          }
          foreach ($dues as $item2) {
            if ($item1['date'] === $item2['due_date']) {
              if ($item2['is_due'] === 1) {
                $item1['is_due'] = 1;
              }
              break;
            }
          }
        }

        foreach ($rs_dates as $key => $item) {
          if ($item["status"] == 1) {
            unset($rs_dates[$key]);
          }
        }

        $last_array = end($rs_dates);
        if (is_array($last_array)) {
          $current_scheme_latest_month[] = [
            'scheme_name' => $user_subscription->scheme->{'title_' . app()->getLocale()},
            'date' => $last_array['date'] ?? '',
            'amount' => $last_array['amount'] ?? 0,
          ];
        }
      } else {
        $last_array = end($result_dates);
        if ($last_array) {
          $current_scheme_latest_month[] = [
            'scheme_name' => $user_subscription->scheme->{'title_' . app()->getLocale()},
            'date' => $last_array,
            'amount' => $user_subscription->subscribe_amount ?? 0,
          ];
        }
      }

      return response()->json([
        'my_payments' => $current_scheme_latest_month,
        'status' => '1'
      ]);
    } catch (\Exception $e) {
      return response()->json([
        'message' => 'An error occurred: ' . $e->getMessage(),
        'status' => '0'
      ], 500);
    }
  }



  public function maturedSubscription()
  {
    $userId = auth()->user()->id;
    $subscription = UserSubscription::with('scheme.schemeType')
      ->where('user_id', $userId)
      ->where('is_closed', true)
      ->latest()
      ->get();

    $response = [];
    $startDate = Carbon::parse($subscription->start_date)->format('Y-m-d');
    $endDate = Carbon::parse($subscription->end_date)->format('Y-m-d');

    $totalFlexibleSchemeAmount = DepositPeriod::whereHas('deposit', function ($query) use ($subscription) {
      $query->where('subscription_id', $subscription->id)
        ->where('status', true);
    })
      ->where('due_date', '>=', $startDate)
      ->where('due_date', '<=', $endDate)
      ->where('status', true)
      ->sum('scheme_amount');

    $goldWeight = 0;
    if ($subscription->scheme->schemeType->id == SchemeType::GOLD_PLAN) {
      $latestGoldRate = GoldRate::select('date_on', 'per_gram')->latest('date_on')->first();
      $goldWeight = $totalFlexibleSchemeAmount / $latestGoldRate->per_gram;
    }

    $response[] = [
      'message' => trans("Your Gold scheme matured"),
      'total_amount_paid' => number_format($totalFlexibleSchemeAmount, 2),
      'contact_message' => 'Contact jewellery for more information'
    ];

    if ($subscription->scheme->schemeType->id == SchemeType::GOLD_PLAN) {
      $response['total_grams_earned'] = number_format($goldWeight, 2);
      $response['unit'] = 'g';
    }

    return response()->json([
      'success' => true,
      'message' => 'Matured Subscription retrieved successfully',
      'data' => $response
    ]);
  }

  public function claimedSubscription()
  {
    $userId = auth()->user()->id;
    $subscription = UserSubscription::with('scheme.schemeType')
      ->where('user_id', $userId)
      ->where('is_closed', true)
      ->where('claim_status', true)
      ->latest()
      ->first();

    $schemeName = $subscription->scheme->{'title_' . app()->getLocale()};
    $startDate = Carbon::parse($subscription->start_date)->format('Y-m-d');
    $endDate = Carbon::parse($subscription->end_date)->format('Y-m-d');

    $totalFlexibleSchemeAmount = DepositPeriod::whereHas('deposit', function ($query) use ($subscription) {
      $query->where('subscription_id', $subscription->id)->where('status', true);
    })
      ->where('due_date', '>=', $startDate)
      ->where('due_date', '<=', $endDate)
      ->where('status', true)
      ->sum('scheme_amount');

    $goldWeight = 0;
    if ($subscription->scheme->schemeType->id == SchemeType::GOLD_PLAN) {
      $latestGoldRate = GoldRate::select('date_on', 'per_gram')->latest('date_on')->first();
      $goldWeight = $totalFlexibleSchemeAmount / $latestGoldRate->per_gram;
    }

    $response = [
      'message' => trans("You have successfully Claimed your $schemeName"),
      'total_amount' => number_format($totalFlexibleSchemeAmount, 2),
    ];

    if ($subscription->scheme->schemeType->id == SchemeType::GOLD_PLAN) {
      $response['total_grams'] = number_format($goldWeight, 2);
      $response['unit'] = 'g';
    }

    return response()->json([
      'success' => true,
      'message' => 'Claimed Subscription retrieved successfully',
      'data' => $response
    ]);
  }

  public function scheme_history()
  {
    $id = auth()->user()->id;

    $user_subscription = UserSubscription::with('scheme', 'scheme.schemeType')
      ->where('user_id', $id)
      // ->where('is_closed', false)
      ->orderBy('created_at', 'desc')
      ->first();

    // $user_subscription_deposits =  Deposit::where('subscription_id', $user_subscription->id)->get();
    $user_subscription_deposits = Deposit::with(['transactions' => function ($query) {
      $query->select('deposit_id', 'razorpay_payment_id');
    }])->where('subscription_id', $user_subscription->id)->get();

    $sum = 0;
    $dates_array = [];
    foreach ($user_subscription_deposits as  $user_subscription_deposit) {

      if ($user_subscription_deposit->status == '1') {
        $sum += $user_subscription_deposit->final_amount;
        $transaction = $user_subscription_deposit->transactions->first();

        $razorpay_payment_id = $transaction ? $transaction->razorpay_payment_id : null;



        $schemeTitle = $user_subscription->scheme->{'title_' . app()->getLocale()};
        $dates_array[] = [
          'deposit_id' => $user_subscription_deposit->id,
          'scehme' =>    $schemeTitle,
          'date' => $user_subscription_deposit->paid_at,
          'amount' => $user_subscription_deposit->final_amount,
          'razorpay_payment_id' => $razorpay_payment_id,

        ];
      }
    }
    return response()->json([
      'wallet' => $sum,
      'scheme_history' => $dates_array,
      'status' => '1'
    ], 200, [], JSON_UNESCAPED_UNICODE);
  }

  public function paymentDetails($dep_id)
  {
    $deposit = Deposit::with([
      'userSubscription.user.customer',
      'userSubscription.scheme',
      'transactions',
    ])->findOrFail($dep_id);

    $depositDetails = [
      'id' => $deposit->id,
      'name' => $deposit->userSubscription->user->name ?? null,
      'scheme' => $deposit->userSubscription->scheme->{'title_' . app()->getLocale()} ?? null,
      'mobile' => $deposit->userSubscription->user->customer->mobile ?? '',
      'email' => $deposit->userSubscription->user->email ?? '',
      'referralCode' => $deposit->userSubscription->user->customer->referrel_code ?? null,
      'transactionId' => $deposit->transactions->first()->razorpay_payment_id  ?? $deposit->order_id,
      'paidAmount' => $deposit->final_amount,
      'paymentMethod' => 'Online Payment',
      'paymentDate' => $deposit->paid_at,
    ];

    return response()->json([
      'data' => $depositDetails,
      'status' => '1'
    ]);
  }

  public function token(Request $request)
  {

    $validated = $request->validate([
      'token_id' => 'required',
      'device_type' => 'required|string',
    ]);


    $customer = Customer::where('user_id', auth()->id())->first();

    if ($customer) {

      $customer->update([
        'token_id' => $validated['token_id'],
        'device_type' => $validated['device_type'],
      ]);

      $title = "Device Token Saved";
      $body = "Your device token has been successfully saved.";




      return response()->json([
        'status' => '1',
      ]);
    }


    return response()->json([
      'message' => 'Customer not found.',
    ], 404);
  }


  public function sendNotification()
  {

    $users = UserSubscription::with(['deposits', 'user', 'schemeSetting', 'user.customer'])
      ->get();


    $messaging = (new Factory)
      ->withServiceAccount(storage_path('app/google/madhurima-gold-a20be1d55954.json'))
      ->createMessaging();


    $currentMonth = now()->format('Y-m');


    $due_duration = $users->first()->schemeSetting->due_duration;


    $due_date = now()->startOfMonth()->addDays($due_duration - 1);


    $notification_date = $due_date->copy()->subDays(3);


    if (now()->between($notification_date->startOfDay(), $due_date->endOfDay())) {


      $tokensToNotify = [];

      foreach ($users as $user_subscription) {

        $depositExistsThisMonth = $user_subscription->deposits->contains(function ($deposit) use ($currentMonth) {
          return Carbon::parse($deposit->paid_at)->format('Y-m') === $currentMonth && $deposit->status == 0;
        });


        if (!$depositExistsThisMonth) {

          $token_id = $user_subscription->user->customer->token_id ?? null;


          if ($token_id && !in_array($token_id, $tokensToNotify)) {
            $tokensToNotify[] = $token_id; // Collect token IDs
          } elseif (!$token_id) {

            Log::warning("Token ID is missing for user: {$user_subscription->user_id}");
          }
        }
      }


      if (!empty($tokensToNotify)) {

        $title = "Payment Reminder";
        $body = "Your payment is due on {$due_date->format('Y-m-d')}. Please make the payment before the due date.";


        $notification = Notification::create($title, $body);


        $message = CloudMessage::new()
          ->withNotification($notification)
          ->withData([]);


        try {

          $response = $messaging->sendMulticast($message, $tokensToNotify);


          Log::info("One notification sent to multiple users.", ['response' => $response]);
        } catch (\Throwable $e) {

          Log::error("Failed to send notification to users", ['error' => $e->getMessage()]);
        }
      }
    }

    return response()->json(['message' => 'Notification sent successfully!']);
  }

  public function isProfileComplete()
  {
    $user = auth()->user();

    if (!$user) {
      return false; // Return false if no authenticated user
    }

    $fields = [
      'name' => $user->name,
      'mobile' => $user->customer->mobile ?? null,
      'aadhar_number' => $user->customer->aadhar_number ?? null,
      'address' => $user->address->address ?? null,
      'country_id' => $user->address->country_id ?? null,
      'state_id' => $user->address->state_id ?? null,
      'district_id' => $user->address->district_id ?? null,
      'pincode' => $user->address->pincode ?? null,
      'referrel_code' => $user->customer->referrel_code ?? null,
      'nominee_name' => $user->nominee->name ?? null,
      'nominee_relationship' => $user->nominee->relationship ?? null,
      'nominee_phone' => $user->nominee->phone ?? null,
      'password' => $user->password,
      'is_verified' => $user->customer->is_verified ?? null,
      'status' => $user->customer->status ?? null,
    ];

    foreach ($fields as $field) {
      if (empty($field)) {
        return false;
      }
    }

    return true;
  }


  public function completedSubscriptions()
  {
    $userSubscriptions = UserSubscription::with(['scheme' => function ($query) {
      $query->with(['schemeType' => function ($query) {
        $query->select('id', 'title');
      }])->select('id', 'title_' . app()->getLocale(), 'scheme_type_id');
    }])
      ->select('id', 'scheme_id', 'subscribe_amount', 'start_date', 'end_date', 'reason', 'is_closed', 'status')
      ->where('user_id', auth()->user()->id)
      ->where('is_closed', true)
      ->latest()
      ->get();

    $flattenedSubscriptions = $userSubscriptions->map(function ($subscription) {

      return [
        'id' => $subscription->id,
        'scheme_id' => $subscription->scheme_id,
        'subscribe_amount' => $subscription->subscribe_amount,
        'start_date' => $subscription->start_date,
        'end_date' => $subscription->end_date,
        'reason' => $subscription->reason,
        'is_closed' => $subscription->is_closed,
        'status' => $subscription->status,
        'scheme_title' => $subscription->scheme->{'title_' . app()->getLocale()},
        'scheme_type_title' => $subscription->scheme->schemeType->title ?? null,
      ];
    });

    return response()->json([
      'success' => true,
      'message' => 'Closed Subscriptions Retrieved successfully',
      'data' => $flattenedSubscriptions,
    ]);
  }
}
