<?php

namespace App\Http\Controllers;

use App\Models\Addres;
use App\Models\Businesss;
use App\Models\Expenses;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Locations;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

class Admin extends Controller
{
    public function insertBusiness(Request $request)
    {
        $rules = [
            'business_name' => 'required',
            'gst' => 'required',
            'email' => 'required|email',
            'phone' => 'required',
            'alternate_phone' => 'nullable',
            'owner_name' => 'required',
        ];
    
        $validator = Validator::make($request->all(), $rules);
    
        if ($validator->fails()) {
            return $validator->errors();
        }
        try {
            $business = new Businesss();
            $business->business_name = $request->business_name;
            $business->gst = $request->gst;
            $business->email = $request->email;
            $business->phone = $request->phone;
            $business->alternate_phone = $request->input('alternate_phone');
            $business->owner_name = $request->owner_name;
            if ($request->hasFile('logo')) {
                $file = $request->file('logo')->store('public/logo');
                $business->logo = $file;
            }
            $business->save();
            return response([
                'status' => true,
                'message' => 'Business created successfully.',
                'data' => $business
            ], 200);
        } catch (\Exception $e) {
            return response([
                'status' => false,
                'message' => 'Failed to insert business.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function insertLocation(Request $request)
{
    $rules = [
        'location_name' => 'required',
        'business_id' => 'required',
        'address' => 'required',
        'email' => 'required|email',
        'alternate_phone' => 'nullable',
        'phone' => 'required',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return $validator->errors();
    }

    try {
        $location = new Locations();
        $location->location_name = $request->location_name;
        $location->business_id = $request->business_id;
        $location->address = $request->address;
        $location->email = $request->email;
        $location->alternate_phone = $request->input('alternate_phone');
        $location->phone = $request->phone;
        $location->save();
        return response([
            'status' => true,
            'message' => 'Location created successfully.',
            'data' => $location
        ], 200);
    } catch (\Exception $e) {
        return response([
            'status' => false,
            'message' => 'Failed to insert location.',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function createInvoice(Request $request)
{
    $rules = [
        'type' => 'required|in:normal,performa',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
    }

    try {
        DB::beginTransaction();

        $nextSerialNo = $this->getNextSerialNumber($request->type, $request->location_id);

        $invoice = Invoice::create([
            'serial_no' => $nextSerialNo,
            'name' => $request->name,
            'mobile_number' => $request->mobile_number,
            'customer_type' => $request->customer_type,
            'doc_type' => $request->doc_type,
            'doc_no' => strtoupper($request->doc_no),
            'business_id' => $request->business_id,
            'location_id' => $request->location_id,
            'payment_mode' => $request->payment_mode,
            'billing_address_id' => $request->billing_address_id,
            'shipping_address_id' => $request->shipping_address_id,
            'type' => $request->type,
            'is_completed' => 0,
            'invoice_date' => $request->invoice_date,
        ]);

        DB::commit();

        return response()->json([
            'status' => true, 
            'message' => 'Invoice created successfully.', 
            'data' => $invoice
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => false, 
            'message' => 'Failed to create invoice.', 
            'error' => $e->getMessage()
        ], 500);
    }
}

private function getNextSerialNumber($type, $location_id)
{
    if ($type === 'performa') {
        return null;
    }
    if(!(Invoice::where('type', '!=', 'performa')->where('location_id',$location_id)->first())){
        return 1;
    }
    return Invoice::where('type', '!=', 'performa')->where('location_id',$location_id)
        ->max('serial_no') + 1;
}
    public function getAllInvoices(Request $request){
    try {
        $query = Invoice::query();
        $query->orderBy('created_at', 'desc');

                  // Filter by day
                  if($request->has('day')){
                    $query->whereDate('invoice_date', $request->day);
                }
        
                // Filter by month
                if($request->has('month')){
                    $query->whereMonth('invoice_date', $request->month);
                }
        
                // Filter by week
                if($request->has('week_start')){
                    // Assuming the week is passed as an array with start and end dates
                    $query->whereBetween('invoice_date', [$request->week_start, $request->week_end]);
                }
        
                // Filter by year
                if($request->has('year')){
                    $query->whereYear('invoice_date', $request->year);
                }

        // If location_id and business_id are provided, filter by both
        if ($request->has('location_id') && $request->has('business_id')) {
            $query->where('location_id', $request->location_id)
                  ->where('business_id', $request->business_id);
        }
        // If only business_id is provided, filter by business_id
        elseif ($request->has('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        // Retrieve the invoices along with the total price
        $invoices = $query->withSum('items', 'price_of_all')->get();

        // Check if any invoices are found
        if ($invoices->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No invoices found.'], 404);
        }

        // Return the invoices
        return response()->json(['status' => true, 'message' => 'Invoices retrieved successfully.', 'data' => $invoices], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to retrieve invoices.', 'error' => $e->getMessage()], 500);
    }
}
    public function getDetailedInvoice($invoiceId){
    try {
        // Fetch the invoice along with its related items and product details
        $invoice = Invoice::with(['items.product', 'billingAddress', 'shippingAddress'])->find($invoiceId);

        // Check if the invoice is found
        if (!$invoice) {
            return response()->json(['status' => false, 'message' => 'Invoice not found.'], 404);
        }

        // Return the detailed invoice
        return response()->json(['status' => true, 'message' => 'Invoice details retrieved successfully.', 'data' => $invoice], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to retrieve invoice details.', 'error' => $e->getMessage()], 500);
    }
}

public function getDetailedInvoiceWeb($invoiceId)
{
    try {
        // Fetch the invoice along with its related items, product details, and addresses
        $invoice = Invoice::with(['items.product', 'billingAddress', 'shippingAddress'])
            ->leftJoin('businessses', 'invoices.business_id', '=', 'businessses.id')
            ->leftJoin('locations', 'invoices.location_id', '=', 'locations.id')
            ->select(
                'invoices.*',
                'businessses.gst as business_gst',
                'businessses.business_name as business_name',
                'businessses.logo as business_logo',
                'locations.location_name',
                'locations.address as location_address',
                'locations.email as location_email',
                'locations.phone as location_phone',
                'locations.alternate_phone as location_alternate_phone'
            )
            ->find($invoiceId);

        // Check if the invoice is found
        if (!$invoice) {
            return response()->json(['status' => false, 'message' => 'Invoice not found.'], 404);
        }

        // Fetch bank details for the business and location
        $bankDetails = DB::table('banks')
            ->where('business_id', $invoice->business_id)
            ->where('location_id', $invoice->location_id)
            ->first();

        // Prepare business and location info
        $businessInfo = [
            'name' => $invoice->business_name ?? 'Archit Art Gallery',
            'address' => $invoice->location_address ?? 'Shop No: 28 Kirti Nagar Furniture Block, Kirti Nagar, New Delhi – 110015',
            'phone' => $invoice->location_phone ?? '+91- 9868 200 002',
            'alternate_phone' => $invoice->location_alternate_phone ?? '+91- 9289 388 374',
            'gst' => $invoice->business_gst ?? '07AADPA2039E1ZF',
            'logo' => $invoice->business_logo ?? null
        ];

        // Add business info and bank details to the invoice data
        $invoice->business_info = $businessInfo;
        $invoice->bank_details = $bankDetails;

        // Return the detailed invoice
        return response()->json([
            'status' => true,
            'message' => 'Invoice details retrieved successfully.',
            'data' => $invoice
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to retrieve invoice details.',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getBulkInvoicesWeb(Request $request)
{
    try {
        // Start with a base query
        $query = Invoice::with(['items.product', 'billingAddress', 'shippingAddress'])
            ->leftJoin('businessses', 'invoices.business_id', '=', 'businessses.id')
            ->leftJoin('locations', 'invoices.location_id', '=', 'locations.id')
            ->select(
                'invoices.*',
                'businessses.gst as business_gst',
                'locations.location_name',
                'locations.address as location_address',
                'locations.email as location_email',
                'locations.phone as location_phone',
                'locations.alternate_phone as location_alternate_phone'
            )
            ->where('invoices.is_completed', 1);

        // Apply date range filter if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('invoices.invoice_date', [$request->start_date, $request->end_date]);
        }

        // Apply min amount filter if provided
        if ($request->has('min_amount')) {
            $query->where('invoices.total_amount', '>=', $request->min_amount);
        }

        // Apply max amount filter if provided
        if ($request->has('max_amount')) {
            $query->where('invoices.total_amount', '<=', $request->max_amount);
        }

        // Apply type filter if provided
        if ($request->has('type')) {
            $query->where('invoices.type', $request->type);
        }

        // Apply payment mode filter if provided
        if ($request->has('payment_mode')) {
            $query->where('invoices.payment_mode', $request->payment_mode);
        }

        // Apply business_id filter if provided
        if ($request->has('business_id')) {
            $query->where('invoices.business_id', $request->business_id);
        }

        // Apply location_id filter if provided
        if ($request->has('location_id')) {
            $query->where('invoices.location_id', $request->location_id);
        }

        // Execute the query and get the results
        $invoices = $query->get();

        // Check if any invoices are found
        if ($invoices->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No invoices found matching the criteria.'], 404);
        }

        // Process each invoice to add business_info
        $processedInvoices = $invoices->map(function ($invoice) {
            $invoice->business_info = [
                'name' => $invoice->location_name ?? 'Archit Art Gallery',
                'address' => $invoice->location_address ?? 'Shop No: 28 Kirti Nagar Furniture Block, Kirti Nagar, New Delhi – 110015',
                'phone' => $invoice->location_phone ?? '+91- 9868 200 002',
                'alternate_phone' => $invoice->location_alternate_phone ?? '+91- 9289 388 374',
                'gst' => $invoice->business_gst ?? '07AADPA2039E1ZF'
            ];
            
            // Remove the additional fields from the main invoice object
            unset($invoice->business_gst, $invoice->location_name, $invoice->location_address, 
                  $invoice->location_email, $invoice->location_phone, $invoice->location_alternate_phone);
            
            return $invoice;
        });

        // Return the detailed invoices
        return response()->json([
            'status' => true, 
            'message' => 'Invoices retrieved successfully.', 
            'data' => $processedInvoices
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false, 
            'message' => 'Failed to retrieve invoices.', 
            'error' => $e->getMessage()
        ], 500);
    }
}
    public function removeInvoice($id){
    try {
        // Find the invoice by ID
        $invoice = Invoice::find($id);

        // If invoice not found, return error
        if (!$invoice) {
            return response()->json(['status' => false, 'message' => 'Invoice not found.'], 404);
        }

        // Delete the invoice
        $invoice->delete();

        return response()->json(['status' => true, 'message' => 'Invoice deleted successfully.'], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to delete invoice.', 'error' => $e->getMessage()], 500);
    }
}

public function editInvoice(Request $request)
{
    $rules = [
        'id' => 'required|exists:invoices,id',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
    }

    try {
        DB::beginTransaction();

        $invoice = Invoice::findOrFail($request->id);

        $updatableFields = [
            'name', 'mobile_number', 'customer_type', 'doc_type', 'doc_no',
            'business_id', 'location_id', 'payment_mode', 'billing_address_id',
            'shipping_address_id', 'is_completed', 'invoice_date'
        ];

        $updateData = $request->only($updatableFields);
        
        // Convert doc_no to uppercase if it exists in the request
        if (isset($updateData['doc_no'])) {
            $updateData['doc_no'] = strtoupper($updateData['doc_no']);
        }

        $invoice->fill($updateData);
        $invoice->save();

        DB::commit();

        return response()->json([
            'status' => true,
            'message' => 'Invoice updated successfully.',
            'data' => $invoice
        ], 200);

    } catch (ModelNotFoundException $e) {
        DB::rollBack();
        return response()->json([
            'status' => false,
            'message' => 'Invoice not found.',
        ], 404);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => false,
            'message' => 'Failed to update invoice.',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function addProduct(Request $request)
{
    $rules = [
        'invoice_id' => 'required|exists:invoices,id',
        'hsn_code' => 'required',
        'name' => 'required',
        'price' => 'required|numeric|min:0',
        'quantity' => 'required|integer|min:1',
     
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
    }

    try {
        DB::beginTransaction();

        $invoice = Invoice::findOrFail($request->invoice_id);
        $product = $this->getOrCreateProduct($request->hsn_code, $request->name, $invoice);
        $address = Addres::where('invoice_id', $request->invoice_id)->first();

        $item = new Item();
        $item->product_id = $product->id;
        $item->invoice_id = $request->invoice_id;
        $item->quantity = $request->quantity;
        $item->is_gst = $request->is_gst;

        $this->calculateItemPrice($item, $invoice, $request->price, $request->is_gst);
        $this->calculateGST($item, $address, $invoice->type);

        $item->price_of_all = $this->calculateTotalPrice($item);
        $item->save();

        $this->updateInvoiceTotals($invoice, $item);

        DB::commit();
        $formattedItem = $this->formatItemResponse($item, $invoice);
        return response()->json([
            'status' => true,
            'message' => 'Product added successfully.',
            'data' => $formattedItem
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['status' => false, 'message' => 'Failed to add product.', 'error' => $e->getMessage()], 500);
    }
}

private function getOrCreateProduct($hsnCode, $name, $invoice)
{
    return Product::firstOrCreate(
        [
            'hsn_code' => $hsnCode,
            'business_id' => $invoice->business_id,
            'location_id' => $invoice->location_id
        ],
        [
            'name' => $name
        ]
    );
}

private function calculateItemPrice(Item $item, Invoice $invoice, $price, $isGst)
{
    if ($invoice->type == 'normal' && $isGst) {
        $item->price_of_one = round($price / 1.18, 2);
    } else {
        $item->price_of_one = $price;
    }
}

private function calculateGST(Item $item, ?Addres $address, $invoiceType)
{
    if ($invoiceType == 'normal') {
        $basePrice = $item->price_of_one * $item->quantity;
        $isDelhi = $address && strtolower($address->state) == 'delhi';

        if ($isDelhi) {
            $item->dgst = $item->cgst = round(0.09 * $basePrice, 2);
            $item->igst = 0;
        } else {
            $item->dgst = $item->cgst = 0;
            $item->igst = round(0.18 * $basePrice, 2);
        }
    } else {
        $item->dgst = $item->cgst = $item->igst = 0;
    }
}

private function calculateTotalPrice(Item $item)
{
    return round($item->price_of_one * $item->quantity + $item->dgst + $item->cgst + $item->igst, 2);
}

private function updateInvoiceTotals(Invoice $invoice, Item $item)
{
    $invoice->total_dgst += $item->dgst;
    $invoice->total_cgst += $item->cgst;
    $invoice->total_igst += $item->igst;
    $invoice->total_amount += $item->price_of_all;
    $invoice->save();
}

private function formatItemResponse(Item $item, Invoice $invoice) {
    return array_merge(
        $item->toArray(),
        [
            't_dgst' => round($invoice->total_dgst, 2),
            't_cgst' => round($invoice->total_cgst, 2),
            't_igst' => round($invoice->total_igst, 2),
            'total_amount' => round($invoice->total_amount, 2),
            'total_ex_gst_amount' => round($invoice->total_amount - $invoice->total_dgst - $invoice->total_cgst - $invoice->total_igst, 2),
        ]
    );
}
    public function getItemsByInvoiceId(Request $request){
    // Validation rules for invoice ID
    $rules = [
        'invoice_id' => 'required|exists:invoices,id',
    ];

    // Validate the incoming request
    $validator = Validator::make($request->all(), $rules);

    // If validation fails, return errors
    if ($validator->fails()) {
        return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
    }

    try {
        // Fetch items associated with the provided invoice ID
        $items = Item::where('invoice_id', $request->invoice_id)->get();

        // Check if any items are found
        if ($items->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No items found for the provided invoice ID.'], 404);
        }

        // Return the items
        return response()->json(['status' => true, 'message' => 'Items retrieved successfully.', 'data' => $items], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to retrieve items.', 'error' => $e->getMessage()], 500);
    }
}

    public function editProduct(Request $request){
    // Validation rules for updating item
    $rules = [
        'hsn_code' => 'required',
        'name' => 'required',
        'price' => 'required',
        'quantity' => 'required',
        'item_id' => 'required',
    ];

    // Validate the incoming request
    $validator = Validator::make($request->all(), $rules);

    // If validation fails, return errors
    if ($validator->fails()) {
        return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
    }

    try {
        // Find the item by its ID
        $item = Item::findOrFail($request->item_id);

        // Find or create the product based on HSN code
        if ($product = Product::where('hsn_code', $request->hsn_code)->first()) {
            $item->product_id = $product->id;
        } else {
            $product = new Product();
            $product->name = $request->name;
            $product->hsn_code = $request->hsn_code;
            $product->save();
            $item->product_id = $product->id;
        }

        // Update item details
        $item->quantity = $request->quantity;
        if($request->is_gst==1){
            $item->price_of_one = $request->price;
        }else{
            $item->price_of_one = $request->price/1.18;
        }
        $address = Addres::where('invoice_id', $request->invoice_id)->first();
        // Calculate GST based on whether it's inclusive or exclusive
        if($request->type=='normal'){
                  if ($request->is_gst == 1) {
            // Inclusive GST
            // Check if the address place is Delhi
           
            if ($address->state == 'delhi') {
                $item->dgst = (0.09 * $item->price_of_one) * $request->quantity; // 9% GST for Delhi
                $item->cgst = (0.09 * $item->price_of_one) * $request->quantity; // 9% GST for Delhi
                $item->igst = 0; // No IGST for Delhi
            } else {
                $item->dgst = 0; // No DGST for other states
                $item->cgst = 0; // No CGST for other states
                $item->igst = (0.18 * $item->price_of_one) * $request->quantity; // 18% IGST for other states
            }
        } else {
            // Exclusive GST
            if ($address->state == 'delhi') {
                $item->dgst = (0.09 * $item->price_of_one) * $request->quantity; // 9% GST for Delhi
                $item->cgst = (0.09 * $item->price_of_one) * $request->quantity; // 9% GST for Delhi
                $item->igst = 0; // No IGST for Delhi
            } else {
                $item->dgst = 0; // No DGST for other states
                $item->cgst = 0; // No CGST for other states
                $item->igst = (0.18 * $item->price_of_one) * $request->quantity; // 18% IGST for other states
            }
        }
            
        }else if($request->type=='performa'){
             $item->dgst = 0;
                $item->cgst = 0;
                $item->igst = 0;
            
        }
  

        // Calculate total price of the item
        $item->price_of_all = $item->price_of_one * $request->quantity + $item->dgst + $item->cgst + $item->igst;
        $update_main = Invoice::find($request->invoice_id);
        $update_main->total_dgst = $update_main->total_dgst+$item->dgst;
        $update_main->total_cgst = $update_main->total_cgst+$item->cgst;
        $update_main->total_igst = $update_main->total_igst+$item->igst;
        $update_main->total_amount = $update_main->total_amount + $item->price_of_all;
        $update_main->save();
        // Save the updated item
        $item->save();
        
        return response()->json(['status' => true, 'message' => 'Item updated successfully.', 'data' => $item], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to update item.', 'error' => $e->getMessage()], 500);
    }
}

public function removeItem($item_id)
{
    try {
        DB::beginTransaction();

        $item = Item::findOrFail($item_id);
        $invoice = Invoice::findOrFail($item->invoice_id);

        // Update the main invoice totals
        $invoice->total_dgst -= $item->dgst;
        $invoice->total_cgst -= $item->cgst;
        $invoice->total_igst -= $item->igst;
        $invoice->total_amount -= $item->price_of_all;

        $invoice->save();

        // Delete the item
        $item->delete();

        DB::commit();

        // Create response data with rounded values
        $response_data = new \stdClass();
        $response_data->t_dgst = round($invoice->total_dgst, 2);
        $response_data->t_cgst = round($invoice->total_cgst, 2);
        $response_data->t_igst = round($invoice->total_igst, 2);
        $response_data->total_amount = round($invoice->total_amount, 2);
        $response_data->total_ex_gst_amount = round($invoice->total_amount - $invoice->total_dgst - $invoice->total_cgst - $invoice->total_igst, 2);

        return response()->json([
            'status' => true, 
            'message' => 'Item removed successfully.', 
            'data' => $response_data
        ], 200);

    } catch (ModelNotFoundException $e) {
        DB::rollBack();
        return response()->json([
            'status' => false, 
            'message' => $e->getMessage()
        ], 404);
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'status' => false, 
            'message' => 'Failed to remove item.', 
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function addAddress(Request $request){
    $rules = [
        'state' => 'required',
        'invoice_id' => 'required',
        'type' => 'required',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return $validator->errors();
    }

    try {
        if(Addres::where('invoice_id', $request->invoice_id)->where('type',$request->type)->first()){
            $location = Addres::where('invoice_id', $request->invoice_id)->where('type',$request->type)->first();
            $location->type = $request->type;
            $location->address_1 = $request->address_1;
            $location->address_2 = $request->address_2;
            $location->city = $request->city;
            $location->state = $request->state;
            $location->pincode = $request->pincode;
            $location->save();
        }else{
            $location = new Addres();
            $location->invoice_id = $request->invoice_id;
            $location->type = $request->type;
            $location->address_1 = $request->address_1;
            $location->address_2 = $request->address_2;
            $location->city = $request->city;
            $location->state = $request->state;
            $location->pincode = $request->pincode;
            $location->save();
             
        }
        $update_main = Invoice::find($request->invoice_id);
             if($request->type==0){
                 $update_main->billing_address_id = $location->id;
             }else{
                  $update_main->shipping_address_id = $location->id;
             }
             $update_main->save();
        
        
        return response([
            'status' => true,
            'message' => 'Adress created/Updated successfully.',
            'data' => $location
        ], 200);
    } catch (\Exception $e) {
        return response([
            'status' => false,
            'message' => 'Failed to insert location.',
            'error' => $e->getMessage()
        ], 500);
    }
}
private function getFileExtension($base64Data) {
    $fileInfo = explode(';base64,', $base64Data);
    $mime = str_replace('data:', '', $fileInfo[0]);
    $extension = explode('/', $mime)[1];
    return $extension;
}
public function addExpense(Request $request){
    $rules = [
        'name' => 'required',
        'amount' => 'required',
        'type' => 'required',
    ];

    $validator = Validator::make($request->all(), $rules);

    if ($validator->fails()) {
        return $validator->errors();
    }

    try {
        if($expense = Expenses::find($request->id)){
            $expense->name = $request->name;
            $expense->amount = $request->amount;
            $expense->type = $request->type;
            $expense->business_id = $request->business_id;
            $expense->location_id = $request->location_id;
            $expense->user_id = $request->user_id;
            $expense->save();
            $expense->find($expense->id);
            if ($request->has('file')) {
                $fileData = $request->file; // base64 encoded file data
                $fileName = 'expense_' . time() . '.' . $this->getFileExtension($fileData).'.'.$request->extension;
                $filePath = 'public/expense/' . $fileName;
                \Storage::put($filePath, base64_decode($fileData));
                $expense->file = $filePath;
            }
    
            $expense->save();
        } else {
            $expense = new Expenses();
            $expense->name = $request->name;
            $expense->amount = $request->amount;
            $expense->type = $request->type;
            $expense->business_id = $request->business_id;
            $expense->location_id = $request->location_id;
            $expense->user_id = $request->user_id;
            $expense->save();
            $expense->find($expense->id);
            if ($request->has('file')) {
                $fileData = $request->file; // base64 encoded file data
                $fileName = 'expense_'.$expense->id.'.'.$request->extension;
                $filePath = 'public/expense/' . $fileName;
                \Storage::put($filePath, base64_decode($fileData));
                $expense->file = $filePath;
            }
            $expense->save();
        }
        
        return response([
            'status' => true,
            'message' => 'Expense created/Updated successfully.',
            'data' => $expense
        ], 200);
    } catch (\Exception $e) {
        return response([
            'status' => false,
            'message' => 'Failed to insert expense.',
            'error' => $e->getMessage()
        ], 500);
    }
}
public function getAllExpenses(Request $request){
    $query = Expenses::query();

    // Filter by day
    if($request->has('day')){
        $query->whereDate('created_at', $request->day);
    }

    // Filter by month
    if($request->has('month')){
        $query->whereMonth('created_at', $request->month);
    }

      // Apply payment mode filter if provided
      if ($request->has('business_id')) {
        $query->where('business_id', $request->business_id);
    }
     // Apply payment mode filter if provided
     if ($request->has('location_id')) {
        $query->where('location_id', $request->location_id);
    }

    // Filter by week
    if($request->has('week_start')){
        // Assuming the week is passed as an array with start and end dates
        $query->whereBetween('created_at', [$request->week_start, $request->week_end]);
    }

    // Filter by year
    if($request->has('year')){
        $query->whereYear('created_at', $request->year);
    }

    // Filter by expense type (0 or 1)
    if($request->has('type')){
        $type = $request->type;
        $query->where('type', $type);
    }

        // Filter by name
        if($request->has('name')){
            $name = $request->name;
            $query->where('name', 'like', '%' . $name . '%');
        }

            // Filter by amount range
    if($request->has('amount_min') && $request->has('amount_max')){
        $amountMin = $request->amount_min;
        $amountMax = $request->amount_max;
        $query->whereBetween('amount', [$amountMin, $amountMax]);
    } 

    if($request->has('expense_id')){
        $query->where('id', $request->expense_id);
    }

        // Order by id in descending order
        $query->orderBy('id', 'DESC');


    $expenses = $query->get();

    return response([
        'status' => true,
        'data' => $expenses,
        'message' => "Data Feteched."
    ], 200);
}


// Get expense by ID
public function getExpenseById($id){
    $expense = Expenses::find($id);
    if($expense){
        return response([
            'status' => true,
            'data' => $expense
        ], 200);
    }else{
        return response([
            'status' => false,
            'message' => 'Expense not found.'
        ], 404);
    }
}

// Delete expense by ID
public function deleteExpense(Request $request){
    $expense = Expenses::find($request->id);
    if($expense){
        $expense->delete();
        return response([
            'status' => true,
            'message' => 'Expense deleted successfully.'
        ], 200);
    }else{
        return response([
            'status' => false,
            'message' => 'Expense not found.'
        ], 404);
    }
}

    public function getAddressByInvoiceId(Request $request){
    // Validation rules for invoice ID
    $rules = [
        'invoice_id' => 'required|exists:addres,invoice_id',
    ];

    // Validate the incoming request
    $validator = Validator::make($request->all(), $rules);

    // If validation fails, return errors
    if ($validator->fails()) {
        return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
    }

    try {
        // Fetch the address associated with the provided invoice ID
        $address = Addres::where('invoice_id', $request->invoice_id)->get();

        // Check if the address is found
        if (!$address) {
            return response()->json(['status' => false, 'message' => 'No address found for the provided invoice ID.'], 404);
        }

        // Return the address
        return response()->json(['status' => true, 'message' => 'Address retrieved successfully.', 'data' => $address], 200);
    } catch (\Exception $e) {
        return response()->json(['status' => false, 'message' => 'Failed to retrieve address.', 'error' => $e->getMessage()], 500);
    }
}

public function getSaleReport(Request $request){
    $query = Invoice::query();

    // Filter by day
    if($request->has('day')){
        $query->whereDate('invoice_date', $request->day);
    }

    // Filter by month
    if($request->has('month')){
        $query->whereMonth('invoice_date', $request->month);
    }

    // Filter by week
    if($request->has('week_start')){
        // Assuming the week is passed as an array with start and end dates
        $query->whereBetween('invoice_date', [$request->week_start, $request->week_end]);
    }

    // Filter by year
    if($request->has('year')){
        $query->whereYear('invoice_date', $request->year);
    }

    // Filter by invoice type or performa
    if($request->has('type')){
        $Type = $request->type;
        $query->where('type', $Type);
    }

    // Filter by name
    if($request->has('name')){
        $name = $request->name;
        $query->where('name', 'like', '%' . $name . '%');
    }

    // Filter by amount range
    if($request->has('amount_min') && $request->has('amount_max')){
        $amountMin = $request->amount_min;
        $amountMax = $request->amount_max;
        $query->whereBetween('total_amount', [$amountMin, $amountMax]);
    }

    // Filter by invoice ID
    if($request->has('invoice_id')){
        $query->where('id', $request->invoice_id);
    }

    // Order by id in descending order
    $query->orderBy('id', 'DESC');

    // Get the filtered invoices
    $query->where('is_completed', 1);
    $invoices = $query->get(['id', 'name', 'total_amount', 'invoice_date', 'type']);

    // Calculate total amount and total transactions
    $totalAmount = round($invoices->sum('total_amount'),2);
    $totalTransactions = $invoices->count();

    return response([
        'status' => true,
        'total_amount' => $totalAmount,
        'total_transactions' => $totalTransactions,
        'data' => $invoices,
        'message' => "Data fetched."
    ], 200);
}

public function getExpenseReport(Request $request){
    $query = Expenses::query();

    // Filter by day
    if($request->has('day')){
        $query->whereDate('created_at', $request->day);
    }

    // Filter by month
    if($request->has('month')){
        $query->whereMonth('created_at', $request->month);
    }

    // Filter by week
    if($request->has('week_start')){
        // Assuming the week is passed as an array with start and end dates
        $query->whereBetween('created_at', [$request->week_start, $request->week_end]);
    }

    // Filter by year
    if($request->has('year')){
        $query->whereYear('created_at', $request->year);
    }

    // Filter by expense type (0 or 1)
    if($request->has('type')){
        $type = $request->type;
        $query->where('type', $type);
    }

        // Filter by name
        if($request->has('name')){
            $name = $request->name;
            $query->where('name', 'like', '%' . $name . '%');
        }

          // Apply payment mode filter if provided
     if ($request->has('business_id')) {
        $query->where('business_id', $request->business_id);
    }
     // Apply payment mode filter if provided
     if ($request->has('location_id')) {
        $query->where('location_id', $request->location_id);
    }

            // Filter by amount range
    if($request->has('amount_min') && $request->has('amount_max')){
        $amountMin = $request->amount_min;
        $amountMax = $request->amount_max;
        $query->whereBetween('amount', [$amountMin, $amountMax]);
    } 

    if($request->has('expense_id')){
        $query->where('id', $request->expense_id);
    }

        // Order by id in descending order
        $query->orderBy('id', 'DESC');


    $expenses = $query->get();
        // Calculate total amount and total transactions
        $totalAmount = round($expenses->sum('amount'),2);
        $totalTransactions = $expenses->count();
        $params = [];

        if ($request->day) $params[] = "day=" . $request->day;
        if ($request->month) $params[] = "month=" . $request->month;
        if ($request->year) $params[] = "year=" . $request->year;
        if ($request->week_start) $params[] = "week_start=" . $request->week_start;
        if ($request->week_end) $params[] = "week_end=" . $request->week_end;
        if ($request->type) $params[] = "type=" . $request->type;
        if ($request->name) $params[] = "name=" . $request->name;
        if ($request->amount_min) $params[] = "amount_min=" . $request->amount_min;
        if ($request->amount_max) $params[] = "amount_max=" . $request->amount_max;
        if ($request->business_id) $params[] = "business_id=" . $request->business_id;
        if ($request->location_id) $params[] = "location_id=" . $request->location_id;
        
        $query_string = implode("&", $params);
        
        $excel = "https://business.architartgallery.in/api/expense-excel.php?" . $query_string;
        $pdf = "https://business.architartgallery.in/api/expense-pdf.php?" . $query_string;
    return response([
        'status' => true,
        'total_expense' => $totalAmount,
        'total_transactions' => $totalTransactions,
        'excel' => $excel,
        'pdf' => $pdf,
        'data' => $expenses,
        'message' => "Data Feteched."
    ], 200);
}

public function getPurchaseSaleInvoice(Request $request){
    $query = Invoice::query();

    // Filter by day
    if($request->has('day')){
        $query->whereDate('invoice_date', $request->day);
    }

    // Filter by month
    if($request->has('month')){
        $query->whereMonth('invoice_date', $request->month);
    }

    // Filter by week
    if($request->has('week_start')){
        // Assuming the week is passed as an array with start and end dates
        $query->whereBetween('invoice_date', [$request->week_start, $request->week_end]);
    }

    // Filter by year
    if($request->has('year')){
        $query->whereYear('invoice_date', $request->year);
    }

    // Filter by payment mode
    if($request->has('payment_mode')){
        $paymentMode = $request->payment_mode;
        $query->where('payment_mode', $paymentMode);
    }

    // Filter by invoice type (normal only)
    $query->where('type', 'normal');

    // Filter by name
    if($request->has('name')){
        $name = $request->name;
        $query->where('name', 'like', '%' . $name . '%');
    }

    // Filter by amount range
    if($request->has('amount_min') && $request->has('amount_max')){
        $amountMin = $request->amount_min;
        $amountMax = $request->amount_max;
        $query->whereBetween('total_amount', [$amountMin, $amountMax]);
    }

    // Filter by invoice ID
    if($request->has('invoice_id')){
        $query->where('id', $request->invoice_id);
    }

    // Ensure only completed invoices are considered
    $query->where('is_completed', 1);

    // Order by id in descending order
    $query->orderBy('id', 'DESC');

    // Select only the specified columns
    $invoices = $query->get(['id', 'name', 'total_amount', 'total_igst', 'total_cgst', 'total_dgst', 'invoice_date', 'type']);

    // Calculate total GST, amount excluding GST, and aggregate totals
    $totalGST = 0;
    $totalExcludingGST = 0;
    foreach ($invoices as $invoice) {
        $invoice->total_gst = $invoice->total_igst + $invoice->total_cgst + $invoice->total_dgst;
        $invoice->amount_excluding_gst = $invoice->total_amount - $invoice->total_gst;
        $totalGST += $invoice->total_gst;
        $totalExcludingGST += $invoice->amount_excluding_gst;
    }

    // Calculate total amount and total transactions
    $totalAmount = round($invoices->sum('total_amount'), 2);
    $totalTransactions = $invoices->count();
    $params = [];

    if ($request->business_id) $params['business_id'] = $request->business_id;
    if ($request->location_id) $params['location_id'] = $request->location_id;
    if ($request->day) $params['day'] = $request->day;
    if ($request->month) $params['month'] = $request->month;
    if ($request->year) $params['year'] = $request->year;
    if ($request->week_start) $params['week_start'] = $request->week_start;
    if ($request->week_end) $params['week_end'] = $request->week_end;
    if ($request->amount_min) $params['amount_min'] = $request->amount_min;
    if ($request->amount_max) $params['amount_max'] = $request->amount_max;
    if ($request->payment_mode) $params['payment_mode'] = $request->payment_mode;
    
    $excel_params = $params;
    $excel_params['type'] = 'normal';
    $excel_query = http_build_query($excel_params);
    
    $pdf_params = $params;
    $pdf_params['type'] = 'normal';
    $pdf_params['max_amount'] = $params['amount_max'] ?? null;
    $pdf_params['min_amount'] = $params['amount_min'] ?? null;
    $pdf_params['start_date'] = $params['week_start'] ?? null;
    $pdf_params['end_date'] = $params['week_end'] ?? null;
    unset($pdf_params['amount_max'], $pdf_params['amount_min'], $pdf_params['week_start'], $pdf_params['week_end']);
    $pdf_query = http_build_query($pdf_params);
    
    $excel = "https://business.architartgallery.in/api/invoice-excel.php?" . $excel_query;
    $pdf = "https://invoice.architartgallery.in/invoices.html?" . $pdf_query;    
    return response([
        'status' => true,
        'total_amount' => $totalAmount,
        'total_transactions' => $totalTransactions,
        'total_gst' => round($totalGST, 2),
        'total_excluding_gst' => round($totalExcludingGST, 2),
        'pdf' => $pdf,
        'excel' => $excel,
        'data' => $invoices,
        'message' => "Data fetched."
    ], 200);
}
public function getInvoiceListReport(Request $request)
{
    $query = Invoice::query();

    // Filter by day
    if ($request->has('day')) {
        $query->whereDate('invoice_date', $request->day);
    }

    // Filter by month
    if ($request->has('month')) {
        $query->whereMonth('invoice_date', $request->month);
    }

    // Filter by week
    if ($request->has('week_start') && $request->has('week_end')) {
        $query->whereBetween('invoice_date', [$request->week_start, $request->week_end]);
    }

    // Filter by year
    if ($request->has('year')) {
        $query->whereYear('invoice_date', $request->year);
    }

    // Filter by invoice type or performa
    if ($request->has('type')) {
        $query->where('type', $request->type);
    }

    // Apply payment mode filter if provided
    if ($request->has('payment_mode')) {
        $query->where('payment_mode', $request->payment_mode);
    }

    // Apply business_id filter if provided
    if ($request->has('business_id')) {
        $query->where('business_id', $request->business_id);
    }

    // Apply location_id filter if provided
    if ($request->has('location_id')) {
        $query->where('location_id', $request->location_id);
    }

    // Filter by name
    if ($request->has('name')) {
        $query->where('name', 'like', '%' . $request->name . '%');
    }

    // Filter by amount range
    if ($request->has('amount_min') && $request->has('amount_max')) {
        $query->whereBetween('total_amount', [$request->amount_min, $request->amount_max]);
    }

    // Filter by invoice ID
    if ($request->has('invoice_id')) {
        $query->where('id', $request->invoice_id);
    }
    

    // Order by id in descending order
    $query->orderBy('id', 'DESC');

    // Get the filtered invoices
    $query->where('is_completed', 1);
    $invoices = $query->select([
        DB::raw('CASE WHEN type = "performa" THEN 0 ELSE CAST(serial_no AS SIGNED) END as id'),
        'name',
        'total_amount',
        'invoice_date',
        'total_cgst',
        'total_dgst',
        'total_igst',
        'type',
        DB::raw('ROUND((total_igst + total_dgst + total_cgst), 2) as tgst'),
        DB::raw('ROUND((total_amount - (total_igst + total_dgst + total_cgst)), 2) as amount_wgst')
    ])->get();

    // Calculate total amount and total transactions
    $totalAmount = round($invoices->sum('total_amount'), 2);
    $totalgst = round($invoices->sum('total_igst') + $invoices->sum('total_cgst') + $invoices->sum('total_dgst'), 2);
    $totalTransactions = $invoices->count();

    $excel = "https://business.architartgallery.in/api/invoice-excel.php?" . http_build_query($request->all());
    $pdf = "https://invoice.architartgallery.in/invoices.html?" . http_build_query($request->all());

    return response([
        'status' => true,
        'total_amount' => $totalAmount,
        'total_amount_wgst' => round($totalAmount-$totalgst,2),
        'tgst' => $totalgst,
        'total_transactions' => $totalTransactions,
        'pdf' => $pdf,
        'excel' => $excel,
        'data' => $invoices,
        'message' => "Data fetched."
    ], 200);
}
public function getExistedUser(Request $request)
{
    // Validate the request to ensure either 'name' or 'mobile_number' is provided
    $request->validate([
        'mobile_number' => 'required',
    ]);
    $mobileNumber = $request->input('mobile_number');

    // Query to find the user based on name or mobile number
    $query = Invoice::query();

    if ($mobileNumber) {
        $query->where('mobile_number', 'like', '%' . $mobileNumber . '%');
    }

    // Retrieve the first matching invoice
    $invoice = $query->first();

    // If no invoice found, return an error response
    if (!$invoice) {
        return response([
            'status' => false,
            'message' => 'No user found with the provided name or mobile number.'
        ], 404);
    }

    // Get billing and shipping addres
    $billingAddress = Addres::where('invoice_id', $invoice->id)->where('type', 'billing')->first();
    $shippingAddress = Addres::where('invoice_id', $invoice->id)->where('type', 'shipping')->first();

    // Prepare response data
    $data = [
        'name' => $invoice->name,
        'mobile_number' => $invoice->mobile_number,
        'customer_type' => $invoice->customer_type,
        'doc_no' => strtoupper($invoice->doc_no),
        'billing_id' => $invoice->billing_address_id,
        'shipping_id' => $invoice->shipping_address_id,
        'billing_address' => $billingAddress ? $billingAddress->only(['address_1', 'address_2', 'city', 'state', 'pincode']) : null,
        'shipping_address' => $shippingAddress ? $shippingAddress->only(['address_1', 'address_2', 'city', 'state', 'pincode']) : null,
    ];

    return response([
        'status' => true,
        'data' => $data,
        'message' => 'User data fetched successfully.'
    ], 200);
}

public function dashboardReport(Request $request)
{
    // Helper function to apply date filters
    $applyDateFilters = function ($query) use ($request) {
        if ($request->has('day')) {
            $query->whereDate('invoice_date', $request->day);
        }
        if ($request->has('month')) {
            $query->whereMonth('invoice_date', $request->month);
        }
        if ($request->has('week_start') && $request->has('week_end')) {
            $query->whereBetween('invoice_date', [$request->week_start, $request->week_end]);
        }
        if ($request->has('year')) {
            $query->whereYear('invoice_date', $request->year);
        }
    };

    // Get Sale Report
    $saleQuery = Invoice::query()->where('is_completed', 1)->where('business_id',$request->business_id)->where('location_id',$request->location_id);
    $applyDateFilters($saleQuery);
    $sales = $saleQuery->orderBy('id', 'DESC')->get(['id', 'name', 'total_amount', 'invoice_date', 'type']);
    $actualSaleAmount = round($sales->sum('total_amount'), 2);

    // Get Purchase Sale Invoice Report
    $purchaseSaleQuery = Invoice::query()->where('is_completed', 1)->where('type', 'normal')->where('business_id',$request->business_id)->where('location_id',$request->location_id);
    $applyDateFilters($purchaseSaleQuery);
    $purchaseSales = $purchaseSaleQuery->get(['id', 'total_dgst', 'total_cgst', 'total_igst', 'total_amount']);
    $totalGst = round($purchaseSales->sum(function($invoice) {
        return $invoice->total_dgst + $invoice->total_cgst + $invoice->total_igst;
    }), 2);
    $totalExcludingGst = round($purchaseSales->sum('total_amount') - $totalGst, 2);

    // Get Invoice Report (Item Purchases)
    $itemQuery = Item::query()->where('business_id',$request->business_id)->where('location_id',$request->location_id)
        ->join('invoices', 'items.invoice_id', '=', 'invoices.id')
        ->where('invoices.is_completed', 1)
        ->where('invoices.type', 'normal');
    $applyDateFilters($itemQuery);
    $items = $itemQuery->get(['items.quantity']);
    $totalItemsPurchased = $items->sum('quantity');

    // Get Expenses Report
    $expenseQuery = Expenses::query()->where('business_id',$request->business_id)->where('location_id',$request->location_id);
    // Apply date filters to expenses (assuming expenses have a 'created_at' field)
    if ($request->has('day')) {
        $expenseQuery->whereDate('created_at', $request->day);
    }
    if ($request->has('month')) {
        $expenseQuery->whereMonth('created_at', $request->month);
    }
    if ($request->has('week_start') && $request->has('week_end')) {
        $expenseQuery->whereBetween('created_at', [$request->week_start, $request->week_end]);
    }
    if ($request->has('year')) {
        $expenseQuery->whereYear('created_at', $request->year);
    }
    $expenses = $expenseQuery->get();
    $totalAmount = round($expenses->sum('amount'), 2);

    // Prepare response data
    $data = [
        'actual_sale_amount' => round(($actualSaleAmount-$totalGst),2),
        'total_excluding_gst' => $totalExcludingGst,
        'total_expense' => $totalAmount,
        'total_gst' => $totalGst,
        'total_items_purchased' => $totalItemsPurchased,
        'profit_loss' => null
    ];

    return response([
        'status' => true,
        'data' => $data,
        'message' => 'Dashboard data fetched successfully.'
    ], 200);
}


public function getBulkInvoices(Request $request)
{
    try {
        // Start with a base query
        $query = Invoice::with(['items.product', 'billingAddress', 'shippingAddress'])
            ->where('is_completed', 1);

        // Apply date range filter if provided
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('invoice_date', [$request->start_date, $request->end_date]);
        }

        // Apply min amount filter if provided
        if ($request->has('min_amount')) {
            $query->where('total_amount', '>=', $request->min_amount);
        }

        // Apply max amount filter if provided
        if ($request->has('max_amount')) {
            $query->where('total_amount', '<=', $request->max_amount);
        }

        // Apply type filter if provided
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Apply payment mode filter if provided
        if ($request->has('payment_mode')) {
            $query->where('payment_mode', $request->payment_mode);
        }
        // Apply payment mode filter if provided
        if ($request->has('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        // Apply payment mode filter if provided
        if ($request->has('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        // Execute the query and get the results
        $invoices = $query->get();

        // Check if any invoices are found
        if ($invoices->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No invoices found matching the criteria.'], 404);
        }

        // Return the detailed invoices
        return response()->json([
            'status' => true, 
            'message' => 'Invoices retrieved successfully.', 
            'data' => $invoices
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false, 
            'message' => 'Failed to retrieve invoices.', 
            'error' => $e->getMessage()
        ], 500);
    }
}
public function getBulkInvoicesSelected(Request $request)
{
    try {
        // Start with a base query
        $query = Invoice::with(['items.product', 'billingAddress', 'shippingAddress'])
            ->leftJoin('businessses', 'invoices.business_id', '=', 'businessses.id')
            ->leftJoin('locations', 'invoices.location_id', '=', 'locations.id')
            ->select(
                'invoices.*',
                'businessses.gst as business_gst',
                'locations.location_name',
                'locations.address as location_address',
                'locations.email as location_email',
                'locations.phone as location_phone',
                'locations.alternate_phone as location_alternate_phone'
            )
            ->where('invoices.is_completed', 1);
            
        if ($request->has('ids') && is_array($request->ids)) {
            $query->whereIn('invoices.id', $request->ids);
        }

        // Execute the query and get the results
        $invoices = $query->get();

        // Check if any invoices are found
        if ($invoices->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No invoices found matching the criteria.'], 404);
        }

        // Process each invoice to add business_info
        $processedInvoices = $invoices->map(function ($invoice) {
            $invoice->business_info = [
                'name' => $invoice->location_name ?? 'Archit Art Gallery',
                'address' => $invoice->location_address ?? 'Shop No: 28 Kirti Nagar Furniture Block, Kirti Nagar, New Delhi – 110015',
                'phone' => $invoice->location_phone ?? '+91- 9868 200 002',
                'alternate_phone' => $invoice->location_alternate_phone ?? '+91- 9289 388 374',
                'gst' => $invoice->business_gst ?? '07AADPA2039E1ZF'
            ];
            
            // Remove the additional fields from the main invoice object
            unset($invoice->business_gst, $invoice->location_name, $invoice->location_address, 
                  $invoice->location_email, $invoice->location_phone, $invoice->location_alternate_phone);
            
            return $invoice;
        });

        // Return the detailed invoices
        return response()->json([
            'status' => true, 
            'message' => 'Invoices retrieved successfully.', 
            'data' => $processedInvoices
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'status' => false, 
            'message' => 'Failed to retrieve invoices.', 
            'error' => $e->getMessage()
        ], 500);
    }
}
 
 
public function getItemizedSalesReport(Request $request)
    {
        // Start building the query
        $query = DB::table('items')
            ->join('products', 'items.product_id', '=', 'products.id')
            ->join('invoices', 'items.invoice_id', '=', 'invoices.id')
            ->where('invoices.business_id', $request->business_id)
            ->where('invoices.location_id', $request->location_id)
            ->where('invoices.is_completed', 1)
            ->where('invoices.type', '=', 'normal');

        // // Filter by price range
        // if ($request->has('price_min')) {
        //     $query->where('items.price_of_one', '>=', $request->price_min);
        // }
        // if ($request->has('price_max')) {
        //     $query->where('items.price_of_one', '<=', $request->price_max);
        // }


                // Filter by day
            if($request->has('day')){
                $query->whereDate('invoices.invoice_date', $request->day);
            }

            // Filter by month
            if($request->has('month')){
                $query->whereMonth('invoices.invoice_date', $request->month);
            }

            

                // Filter by year
            if($request->has('year')){
                $query->whereYear('invoices.invoice_date', $request->year);
            }

            // Filter by week
            if($request->has('week_start')){
                // Assuming the week is passed as an array with start and end dates
                $query->whereBetween('invoices.invoice_date', [$request->week_start, $request->week_end]);
            }

                        // Filter by amount range
            if($request->has('amount_min') && $request->has('amount_max')){
                $amountMin = $request->amount_min;
                $amountMax = $request->amount_max;
                $query->whereBetween('items.price_of_one', [$amountMin, $amountMax]);
            } 


        // Get the results
        $results = $query->select('products.name as product_name', 
                                  'items.price_of_one', 
                                  DB::raw('SUM(items.quantity) as total_quantity'), 
                                  DB::raw('SUM(items.price_of_all) as total_sales'))
                         ->groupBy('products.name', 'items.product_id', 'items.price_of_one')
                         ->orderBy('items.price_of_one')
                         ->get();

        // Calculate total sales

       

        // Prepare data for table
        if ($request->has('purchase_at')) {
            $purchase_at = $request->purchase_at / 100;
            $tableData = $results->map(function ($item) use ($purchase_at) {
                $adjustedPrice = $item->price_of_one * $purchase_at;
                return [
                    'product_name' => $item->product_name,
                    'price_per_item' => round($adjustedPrice, 2),
                    'total_quantity' => $item->total_quantity,
                    'total_sales' => round($item->total_quantity * $adjustedPrice, 2)
                ];
            });
        } else {
            $tableData = $results->map(function ($item) {
                return [
                    'product_name' => $item->product_name,
                    'price_per_item' => $item->price_of_one,
                    'total_quantity' => $item->total_quantity,
                    'total_sales' => round($item->total_quantity * $item->price_of_one, 2)
                ];
            });
        }
        $totalSales = $tableData->sum('total_sales');
        $url = "https://business.architartgallery.in/api/itemised-excel.php?" . http_build_query($request->all());

        return response()->json([
            'status' => true,
            'excel' => $url,
            'total_sales' => round($totalSales, 2),
            'total_items' => $results->sum('total_quantity'),
            'table_data' => $tableData,
            'message' => "Data fetched successfully."
        ], 200);
    }

    public function getlocations(Request $request)
    {
        try {
            // Start with a base query
            $query = Locations::
                where('business_id', $request->business_id);
    
            // Execute the query and get the results
            $invoices = $query->get();
    
            // Check if any invoices are found
            if ($invoices->isEmpty()) {
                return response()->json(['status' => false, 'message' => 'No Location found matching the business Id.'], 404);
            }
    
            
    
            // Return the detailed invoices
            return response()->json([
                'status' => true, 
                'message' => 'Location retrieved successfully.', 
                'data' => $invoices
            ], 200);
    
        } catch (\Exception $e) {
            return response()->json([
                'status' => false, 
                'message' => 'Failed to retrieve invoices.', 
                'error' => $e->getMessage()
            ], 500);
        }
    }

   public function getProducts(Request $request)
    {
        $rules = [
            'business_id' => 'required|exists:businessses,id',
            'location_id' => 'required|exists:locations,id',
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'errors' => $validator->errors()], 400);
        }

        try {
            $products = Product::where('business_id', $request->business_id)
                ->where('location_id', $request->location_id)
                ->where('is_temp', 0)
                ->orderBy('created_at', 'desc')
                ->get();

            if ($products->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No products found for the given business and location.'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Products retrieved successfully.',
                'data' => $products
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve products.',
                'error' => $e->getMessage()
            ], 500);
        }
    }



}
