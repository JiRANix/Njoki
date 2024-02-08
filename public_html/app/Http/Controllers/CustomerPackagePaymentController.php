<?php

namespace App\Http\Controllers;

use App\Models\MpesaProvider;
use App\Models\Payment;
use Illuminate\Http\Request;
use App\Models\CustomerPackagePayment;
use App\Models\CustomerPackage;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class CustomerPackagePaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    public function offline_payment_request(){
        $package_payment_requests = CustomerPackagePayment::where('offline_payment',1)->orderBy('id', 'desc')->paginate(10);
        return view('manual_payment_methods.customer_package_payment_request', compact('package_payment_requests'));
    }

    public function offline_payment_approval(Request $request)
    {
        $package_payment    = CustomerPackagePayment::findOrFail($request->id);
        $package_details    = CustomerPackage::findOrFail($package_payment->customer_package_id);

        $package_payment->approval      = $request->status;
        if($package_payment->save()){
            $user                       = $package_payment->user;
            $user->customer_package_id  = $package_payment->customer_package_id;
            $user->remaining_uploads    = $user->remaining_uploads + $package_details->product_upload;
            if($user->save()){
                return 1;
            }
        }
        return 0;
    }

    public  function  stkPush(Request $request){

        $validator=Validator::make($request->all(),[
            'amount'=>'required',
            'phone'=>'required'
        ]);

        if($validator->fails()){
            return  response()->json([
                'status'=>false,
                'message'=>$validator->errors()->first()
            ]);
        }

        $user = $request->user();
        $amount= $request->amount;
        $phone = $request->phone;

        $ref=uniqid();
        $user=$request->user();
        $response= MpesaProvider::stk_push($phone,$request->amount, $ref, "Payment",$user->id);

       /* Payment::recordPayment($user->id,$ref,$ref,$phone,$request->amount,'Mpesa');*/

        /*  $user_balance = Account::where('user_id',$user->id)->get()[0]->balance;

          $new_user_balance = $user_balance + $amount;

          Account::where('user_id',$user->id)->update([
                      'balance' => $new_user_balance
                  ]);*/



        return response()->json([
            'status' => true,
            'message' => "STK Push successfully sent to {$phone}. Please check your phone",
        ], 200);
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
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
