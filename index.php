<?php
// Import necessary libraries
require 'vendor/autoload.php'; // Assuming you are using Composer for dependencies
use Telegram\Bot\Api;

// Telegram API token
$TELEGRAM_TOKEN = "7711679135:AAErrwekZ0Ym7i_PqWoW9ompV3eTvmAHsC8";
$bot = new Api($TELEGRAM_TOKEN);

// Embedded database for error solutions
$SOLUTIONS_DB = [
    "syntax_error" => "It seems you made a typo. Check your punctuation, such as missing parentheses or periods.",
    "indentation_error" => "Ensure that the indentation in the code is consistent, as Python is sensitive to spaces.",
    "name_error" => "It seems you are using an undefined variable. Make sure you have defined all variables before using them.",
    "type_error" => "Check the data types you are using, as some operations cannot be performed between different types.",
    "value_error" => "Ensure that the input values match the expected types. For example, strings cannot be incorrectly converted to numbers.",
    "index_error" => "Make sure the index you are trying to access is within the range of the list.",
    "key_error" => "Ensure that the key you are trying to access exists in the dictionary.",
];

// Keywords that may help identify the programming language
$language_keywords = [
    "python" => ["def", "import", "print", "class", "lambda"],
    "javascript" => ["function", "let", "const", "console.log"],
    "cpp" => ["#include", "int", "cin", "cout"],
    "java" => ["public", "class", "static", "void"],
    "php" => ["<?php", "echo", "class", "function"],
];

// User data storage
$user_data = [];

// Language choice response
$language_choice_markup = [
    ['text' => "العربية"],
    ['text' => "English"]
];

// Function to detect programming language based on keywords
function detect_language_from_code($code) {
    global $language_keywords;
    foreach ($language_keywords as $lang => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($code, $keyword) !== false) {
                return $lang;
            }
        }
    }
    return "unknown";
}

// Error analysis function
function analyze_code($language, $code) {
    try {
        if ($language == "python") {
            eval($code);
            return "The code works correctly.";
        } elseif ($language == "javascript") {
            $process = popen('node -e ' . escapeshellarg($code), 'r');
            $output = fread($process, 2096);
            pclose($process);
            return $output ? "JavaScript code works correctly." : "JavaScript error: " . $output;
        } elseif ($language == "cpp") {
            file_put_contents("temp.cpp", $code);
            $compile_process = shell_exec("g++ temp.cpp -o temp.out 2>&1");
            if ($compile_process) {
                return "C++ error: " . $compile_process;
            }
            $run_process = shell_exec("./temp.out 2>&1");
            return $run_process ? "C++ code works correctly." : "C++ runtime error: " . $run_process;
        } elseif ($language == "java") {
            file_put_contents("temp.java", $code);
            $compile_process = shell_exec("javac temp.java 2>&1");
            if ($compile_process) {
                return "Java error: " . $compile_process;
            }
            $run_process = shell_exec("java temp 2>&1");
            return $run_process ? "Java code works correctly." : "Java runtime error: " . $run_process;
        } elseif ($language == "php") {
            $process = popen('php -r ' . escapeshellarg($code), 'r');
            $output = fread($process, 2096);
            pclose($process);
            return $output ? "PHP code works correctly." : "PHP error: " . $output;
        } else {
            return "I cannot support this language currently: " . $language;
        }
    } catch (Exception $e) {
        return "An unknown error occurred: " . $e->getMessage();
    }
}

// Error response function
function get_error_solution($error_type) {
    global $SOLUTIONS_DB;
    return $SOLUTIONS_DB[$error_type] ?? "I could not find a solution for this error.";
}

// Responding to users
$bot->commandsHandler();

$bot->command('start', function ($message) use ($bot, $language_choice_markup) {
    $bot->sendMessage($message->chat->id, "Hello! I am a programming assistant. Send me the code and I will analyze any issues and provide you with a solution.\nPlease send the code directly without specifying the language. I will determine it for you automatically.", [
        'reply_markup' => json_encode(['keyboard' => [$language_choice_markup], 'resize_keyboard' => true])
    ]);
});

// Track the user's chosen language
$bot->on(function ($update) use ($bot) {
    $message = $update->getMessage();
    if (in_array(mb_strtolower($message->text), ['العربية', 'english'])) {
        $user_data[$message->chat->id] = [
            "language" => mb_strtolower($message->text) == 'العربية' ? "ar" : "en"
        ];
        $bot->sendMessage($message->chat->id, "Language set successfully. Now send me the code you want to analyze.", [
            'reply_markup' => json_encode(['remove_keyboard' => true])
        ]);
        $bot->registerNextStepHandler($message, 'process_code');
    }
});

// Process the code sent by the user
function process_code($message) {
    global $user_data, $bot;
    $user_input = trim($message->text);
    $user_language = $user_data[$message->chat->id]['language'] ?? "en";

    $language = detect_language_from_code($user_input); // Detect language based on code
    if ($language == "unknown") {
        $bot->replyTo($message, "I could not determine the code language. Please send the code correctly.");
    } else {
        $response = analyze_code($language, $user_input);
        if (strpos($response, "Error") !== false) {
            $error_type = strtolower(explode(":", $response)[0]); // Extract error type
            $solution = get_error_solution($error_type);
            if ($user_language == "ar") {
                $bot->replyTo($message, "Error found in the code: $response\nSolution: $solution");
            } else {
                $bot->replyTo($message, "Error found in the code: $response\nSolution: $solution");
            }
        } else {
            if ($user_language == "ar") {
                $bot->replyTo($message, "Code analysis result:\n$response");
                $bot->sendMessage($message->chat->id, "Corrected code:\n`$user_input`", ['parse_mode' => 'Markdown']);
            } else {
                $bot->replyTo($message, "Code analysis result:\n$response");
                $bot->sendMessage($message->chat->id, "Corrected code:\n`$user_input`", ['parse_mode' => 'Markdown']);
            }
        }
    }
}

// Run the bot
$bot->run();
?>