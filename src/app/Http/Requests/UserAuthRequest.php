<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rules\Password;

class UserAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {

        return [
            'user_name' => 'required|string|max:255',
            'password' => [
                'required',
                'string',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'name' => 'required|string|max:255',
            'portal_user_address' => 'required|string|max:500',
            'email' => 'required|email|max:255|unique:users,email',
            // 'email' => 'required|email|max:255',

            'portal_contact_no' => 'required|digits_between:9,15',
            'portal_contact_no_code'=>'required|integer|max:255',
            'packageType' => 'required|in:INDIVIDUAL,ENTERPRISE',
            'portal_user_city'=>'string|max:255',
            'portal_user_country'=>'integer|max:255',
            'portal_user_zip_code'=>'string|max:255',

            'companyname' => 'required_if:package,ENTERPRISE|string|max:255',
            'companyemail' => 'required_if:package,ENTERPRISE|email|max:255',
            'companycontact_no' => 'required_if:package,ENTERPRISE|digits_between:9,15',
            'companycontact_no_code'=>'required|integer|max:255',
            'companyaddress' => 'required_if:package,ENTERPRISE|string|max:500',
            'companywebsite' => ['nullable','string','max:255','regex:/^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(\/.*)?$/',],
            'companyzip_code'=>'string|max:255',
            'companycountry'=>'integer|max:255',
            'companycity'=>'string|max:255',
            'invitedusers' => 'required|array|min:1|max:5',
            'invitedusers.*.name' => 'required|string|max:255',
            'invitedusers.*.app_user_email' => 'required|email|distinct|unique:users,email',
            // 'invitedusers.*.app_user_email' => 'required|email|distinct',

            'invitedusers.*.admin' => 'required|boolean',
            'invitedusers.*.accountPerson' => 'required|boolean',
            'invitedusers.*.emailError' => 'nullable|string',

        ];
    }

    /**
     * Handle failed validation.
     *
     * @param Validator $validated
     * @throws HttpResponseException
     */
    public function failedValidation(Validator $validated)
    {
        throw new HttpResponseException(response()->json([
            "success" => false,
            "message" => "Validation Error",
            "errors" => $validated->errors(),
        ], 422));
    }
}