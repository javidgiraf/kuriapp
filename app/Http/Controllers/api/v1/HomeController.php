<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use App\Models\GoldRate;
use App\Models\Scheme;
use App\Models\Setting;

class HomeController extends Controller
{
  //
  public function gold_rate()
  {
    $gold_rate = GoldRate::orderBy('id', 'desc')->first();
    $gold_rate['per_gram'] = number_format($gold_rate['per_gram'], 2);
    $gold_rate['per_pavan'] = number_format($gold_rate['per_pavan'], 2);

    return response()->json([
      'gold_rate' => $gold_rate,
      'status' => '1'
    ]);
  }

  public function contactSupport()
  {
    $setting = Setting::first();
    
    $response = [
      'contact_support' => $setting->support_contact,
      'working_time' => $setting->working_time
    ];

    return response()->json([
      'status' => '1',
      'data' => $response
    ]);
  }

  public function all_schemes()
  {
    header('Content-Type: text/html; charset=utf-8');

    $schemes = Scheme::with('schemeType')->get();

    $schemeArrays = $schemes->map(function ($scheme) {
      return [
        'schemeId' => $scheme->id,
        'total_period' => $scheme->total_period,
        'schemeType' => $scheme->schemeType->title,
        'flexibility_duration' => $scheme->schemeType->flexibility_duration,
        'schemeTitle' => $scheme->{'title_' . app()->getLocale()},
        'schemeDescription' => $scheme->{'description_' . app()->getLocale()},
        'termsDescription' => $scheme->{'terms_and_conditions_' . app()->getLocale()},
      ];
    });

    return response()->json([
      'schemes' => $schemeArrays,
      'pdf_url' => url('storage/pdf/schemes-pdf-' . app()->getLocale() . '.pdf'),
      'status' => '1'
    ]);
  }


  public function availableSchemes()
  {
    $schemes = Scheme::query()
      ->with(['schemeType' => function ($query) {

        $query->select('id', 'title', 'shortcode', 'flexibility_duration');
      }])
      ->select('id', 'title_' . app()->getLocale(), 'description', 'total_period', 'status', 'scheme_type_id')
      ->get();

    return response()->json([
      'success' => true,
      'message' => 'Available Schemes Retrieved',
      'data' => $schemes
    ]);
  }

  public function getTermsAndConditions()
  {
    $response = [
      'terms_and_conditions' => Setting::first()->{'terms_and_conditions_' . app()->getLocale()}
    ];

    return response()->json([
      'status' => '1',
      'message' => 'Terms and conditions retrieved',
      'response' => $response
    ]);
  }

  public function schemesPdf()
  {
    $pdfUrl = url('storage/pdf/newpdf.pdf');

    return response()->json([
      'success' => true,
      'message' => 'PDF link retrieved successfully',
      'data' => [
        'pdf_url' => $pdfUrl
      ]
    ]);
  }
}
