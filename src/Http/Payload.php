<?php

namespace YasserElgammal\Green\Http;

use Respect\Validation\Exceptions\ValidationException as RespectValidationException;
use Respect\Validation\Validator as v;
use YasserElgammal\Green\Http\Request;

abstract class Payload extends Request
{
    protected array $errors = [];
    protected array $validatedData = [];

    public function __construct(Request $request)
    {
        parent::__construct(
            $request->query,
            $request->post,
            $request->server,
            $request->files,
            $request->cookies
        );

        $this->attributes = $request->attributes;
        
        $this->prepareForValidation();
        
        if (!$this->authorize()) {
            throw new \Exception('This action is unauthorized.', 403);
        }

        $this->validate();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    abstract public function rules(): array;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        // Hook for subclasses
    }

    /**
     * Validate the request data.
     *
     * @return void
     * @throws ValidationException
     */
    public function validate(): void
    {
        $rules = $this->rules();
        $data = array_merge($this->query, $this->post);
        $this->errors = [];
        $this->validatedData = [];

        foreach ($rules as $field => $validator) {
            $value = $this->input($field);
            
            try {
                $validator->assert($value);
                $this->validatedData[$field] = $value;
            } catch (RespectValidationException $e) {
                // Use custom message if available, otherwise get full message from Respect
                $customMessages = $this->messages();
                $this->errors[$field][] = $customMessages[$field] ?? $e->getMessage();
            }
        }

        if ($this->fails()) {
            throw new ValidationException($this->errors());
        }
    }

    /**
     * Get the validated data.
     *
     * @return array
     */
    public function validated(): array
    {
        return $this->validatedData;
    }

    /**
     * Determine if the validation failed.
     *
     * @return bool
     */
    public function fails(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Get the validation errors.
     *
     * @return array
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Convert the validated data to a DTO (Plain Object).
     *
     * @return object
     */
    public function toDTO(): object
    {
        return (object) $this->validatedData;
    }
}
