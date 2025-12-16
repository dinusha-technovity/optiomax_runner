<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AssetActionMail extends Mailable
{
    use Queueable, SerializesModels;

    public $details;

    public function __construct($details)
    {
        $this->details = $details;
    }

    public function build()
    {
        $appDetailsUrl = env('APPLICATION_URL', config('app.url'));
        
        if ($this->details->queries_type === "usage" || $this->details->queries_type === "asset_group_usage") {
            return $this->subject('Asset Item Usage Base Maintenance Alert')
                        ->view('emails.asset_item_usage_base_alart')
                        ->with('appDetailsUrl', $appDetailsUrl);
        } elseif ($this->details->queries_type === "asset_group_manufacturer" || $this->details->queries_type === "manufacturer") {
            return $this->subject('Asset Item Manufacturer Base Maintenance Alert')
                        ->view('emails.asset_item_manufacturer_base_alart')
                        ->with('appDetailsUrl', $appDetailsUrl);
        } elseif ($this->details->queries_type === "asset_critically_based_schedule_check" || $this->details->queries_type === "asset_item_critically_based_schedule_check") {
            return $this->subject('Asset Item Critically Based Schedule Alert')
                        ->view('emails.asset_item_critically_based_schedule_alart')
                        ->with('appDetailsUrl', $appDetailsUrl);
        } elseif ($this->details->queries_type === "asset_maintenance_tasks_schedule_check" || $this->details->queries_type === "asset_item_maintenance_tasks_schedule_check") {
            return $this->subject('Asset Item Maintenance Tasks Schedule Alert')
                        ->view('emails.asset_item_maintenance_tasks_schedule_alart')
                        ->with('appDetailsUrl', $appDetailsUrl);
        } elseif ($this->details->queries_type === "warranty_expared") {
            return $this->subject('Asset Item Warranty Expires Alart')
                        ->view('emails.asset_item_warranty_expires_alart')
                        ->with('appDetailsUrl', $appDetailsUrl);
        } elseif ($this->details->queries_type === "insurance_expared") {
            return $this->subject('Asset Item Insurance Expires Alart')
                        ->view('emails.asset_item_insurance_expires_alart')
                        ->with('appDetailsUrl', $appDetailsUrl);
        } 
    }
}