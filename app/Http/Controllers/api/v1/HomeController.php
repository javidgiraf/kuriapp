<?php

namespace App\Http\Controllers\api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\GoldRate;
use App\Models\Scheme;

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

  public function all_schemes()
  {
    $schemes = Scheme::with('schemeType')->get();

    $schemeArrays = $schemes->map(function ($scheme) {
      return [
        'schemeId' => $scheme->id,
        'total_period' => $scheme->total_period,
        'schemeType' => $scheme->schemeType->title,
        'flexibility_duration' => $scheme->schemeType->flexibility_duration,
        'schemeTitle' => trans("messages.scheme_title_" . $scheme->scheme_type_id),
        'schemeDescription' => trans("messages.scheme_description_" . $scheme->scheme_type_id),
        'termsDescription' => trans("messages.terms_and_condition_" . $scheme->scheme_type_id),
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
      ->select('id', 'title', 'description', 'total_period', 'status', 'scheme_type_id')
      ->get();

    return response()->json([
      'success' => true,
      'message' => 'Available Schemes Retrieved',
      'data' => $schemes
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
