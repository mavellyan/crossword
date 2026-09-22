<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\Direction;
use App\Enums\Difficulty;
use App\Models\Crossword;
use Illuminate\Validation\Validator;

class StoreCrosswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:255'],
            'main_solution' => ['nullable', 'string', 'min:3', 'max:20', 'regex:/^[A-ZÁÉÍÓÖŐÚÜŰ]+$/u'],
            'entries' => ['required_without:clue_ids', 'prohibits:clue_ids', 'array', 'min:2'],
            'entries.*.clue_id' => ['required_with:entries', 'integer', 'distinct', 'exists:clues,id'],
            'entries.*.direction' => ['required_with:entries', Rule::enum(Direction::class)],
            'entries.*.start_row' => ['required_with:entries', 'integer', 'min:0', 'max:19'],
            'entries.*.start_col' => ['required_with:entries', 'integer', 'min:0', 'max:19'],
            //legacy code rész innentől
            'clue_ids' => ['required_without:entries', 'prohibits:entries', 'array', 'min:1'],
            'clue_ids.*' => ['integer', 'distinct', 'exists:clues,id'],
            'topic_ids' => ['nullable', 'array'],
            'topic_ids.*' => ['integer', 'distinct', 'exists:topics,id'],
            'difficulty' => ['nullable', Rule::enum(Difficulty::class)],
            'is_public' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Configure the validator.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->sometimes('main_solution', ['required'], fn ($input) => !empty($input->clue_ids));
    }
}
