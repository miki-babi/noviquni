 College External API Integration Report & System Verification
The public API key authentication, curriculum discovery, and RAG AI generation services were tested on the Anthropology (Unit 1, Section 1: Definition, Scope and Subject Matter of Anthropology) content.

1. Authentication & Security

Any external system connecting to Noviq needs to provide the API key using one of the following methods:
Method
Syntax
Header (Standard)
X-API-Key: 
Header (Alias)
X-College-API-Key: 
Authorization Header
Authorization: Bearer 
Query Parameter
?apiKey=
Active API Key: noviq_college_pub_afe71862cd8e4b348253ce2c
Base URL: https:///api/public/college (or [http://localhost](http://localhost):/api/public/college)
2. Live Test Results: Anthropology Unit 1, Section 1 Quiz
Endpoint
POST /api/public/college/generate/quiz
Request Payload
json
{
  "sectionId": "6aa93b993b01d26500389f9b",
  "questionCount": 3,
  "refresh": false
}
Live Response Output
json
{
  "success": true,
  "scopeType": "section",
  "scopeId": "6aa93b993b01d26500389f9b",
  "resourceType": "quiz",
  "fromCache": false,
  "data": {
    "questions": [
      {
        "question": "Based on the etymological roots of the word, what are the two Greek components that comprise 'anthropology', and what do they translate to?",
        "options": [
          "‘Anthropo’ (society) and ‘logos’ (history)",
          "‘Anthropos’ (human being/mankind) and ‘logos’ (reason/study/science)",
          "‘Anthe’ (culture) and ‘pology’ (evolution)",
          "‘Anthros’ (primate) and ‘logia’ (classification)"
        ],
        "answerIndex": 1,
        "explanation": "Etymologically, anthropology is a compound of the Greek words ‘anthropos’, translating to 'human being/mankind', and ‘logos’, translating to 'reason/study/science', literally meaning the study or science of humankind.",
        "difficulty": "easy"
      },
      {
        "question": "According to the provided text, what is the precise relationship between human biological and cultural characteristics?",
        "options": [
          "Biological characteristics develop entirely independently of cultural influences.",
          "Cultural characteristics completely determine human physical structures with no reciprocal effect.",
          "Biological and cultural characteristics are inseparable elements, where culture influences human physical structures and vice versa.",
          "Human physical structures dictate cultural evolution in a strictly unidirectional manner."
        ],
        "answerIndex": 2,
        "explanation": "The text explicitly states that biological and cultural characteristics are inseparable elements, emphasizing that culture influences human physical structures and the vice-versa.",
        "difficulty": "medium"
      },
      {
        "question": "Which of the following best captures the unique scope and ultimate goal of anthropology as outlined in the text?",
        "options": [
          "It focuses exclusively on contemporary industrialized societies to understand modern political and economic systems.",
          "It examines only the remote prehistoric past of human physical traits, excluding contemporary cultural practices.",
          "It is restricted to investigating phenotypic characteristics and genetic variations, leaving art and belief systems to other disciplines.",
          "It studies all human beings across time and space, aiming to develop an integrated picture of humankind spanning from genes to art styles."
        ],
        "answerIndex": 3,
        "explanation": "Anthropology possesses a broad scope interested in all human beings (contemporary or past, primitive or civilized) across all dimensions from genes to art styles, with the ultimate goal of developing an integrated picture of humankind.",
        "difficulty": "hard"
      }
    ]
  },
  "generatedAt": "2026-09-15T12:46:32.167Z"
}
 TIP
Performance: First generation grounded against vector chunks took ~4.5s; subsequent calls for this quiz were served directly from cache in 8ms.
3. External API Endpoints Reference
A. Discovery Endpoints (Browsing Curriculum)

1. List Courses

Method: GET /api/public/college/courses
Query Params: ?stream=natural-science (optional)
Response: List of courses with code, credits, stream, and module count.
2. Get Full Module Curriculum (Units & Sections)
Method: GET /api/public/college/modules/:moduleId/curriculum
Example: GET /api/public/college/modules/6aa90f0af1ef868fa5122789/curriculum
Response: Returns the module metadata, array of Units, nested child Sections (_id, sectionNumber, name, vectorEmbedded), and any standalone sections.
B. AI Resource Generation Endpoints
All generation endpoints accept sectionId, unitId, or moduleId as target scope.

1. Generate High-Yield Study Notes

Route: POST /api/public/college/generate/notes
Body:
json
{
  "sectionId": "6aa93b993b01d26500389f9b",
  "refresh": false
}
Response Structure:
json
{
  "success": true,
  "data": {
    "title": "...",
    "summary": "...",
    "keyDefinitions": [{ "term": "...", "definition": "..." }],
    "keyFormulas": [{ "formula": "...", "description": "...", "variables": {} }],
    "corePrinciples": ["..."],
    "commonMistakes": ["..."]
  }
}
2. Generate Interactive Quiz
Route: POST /api/public/college/generate/quiz
Body:
json
{
  "sectionId": "6aa93b993b01d26500389f9b",
  "questionCount": 3,
  "refresh": false
}
Response Structure: Array of multiple-choice questions with question, options (4 choices), answerIndex (0–3), explanation, and difficulty (easy, medium, hard).
3. Generate Formal 2-Part Exam
Route: POST /api/public/college/generate/exam
Body:
json
{
  "sectionId": "6aa93b993b01d26500389f9b",
  "partBCount": 3,
  "refresh": false
}
Response Structure:
partA: Multiple-choice questions with answers and rationales.
partB: Short answer / essay questions with question, points, modelAnswer, and gradingRubric.
4. Generate Active-Recall Flashcards
Route: POST /api/public/college/generate/flashcards
Body:
json
{
  "sectionId": "6aa93b993b01d26500389f9b",
  "cardCount": 15,
  "refresh": false
}
Response Structure: Array of flashcard objects { front, back, hint, category }.
4. Code Samples for External Systems
cURL
bash
curl -X POST "[https://api.yourdomain.com/api/public/college/generate/quiz](https://api.yourdomain.com/api/public/college/generate/quiz)"   
  -H "Content-Type: application/json"   
  -H "X-API-Key: noviq_college_pub_afe71862cd8e4b348253ce2c"   
  -d '{
    "sectionId": "6aa93b993b01d26500389f9b",
    "questionCount": 3
  }'
Python (requests)
python
import requests
API_KEY = "noviq_college_pub_afe71862cd8e4b348253ce2c"
BASE_URL = "[https://api.yourdomain.com/api/public/college](https://api.yourdomain.com/api/public/college)"
headers = {
    "X-API-Key": API_KEY,
    "Content-Type": "application/json"
}

# 1. Fetch Curriculum

curriculum = requests.get(
    f"{BASE_URL}/modules/6aa90f0af1ef868fa5122789/curriculum",
    headers=headers
).json()

# 2. Generate Quiz

quiz = requests.post(
    f"{BASE_URL}/generate/quiz",
    headers=headers,
    json={"sectionId": "6aa93b993b01d26500389f9b", "questionCount": 3}
).json()
print(quiz["data"]["questions"])
TypeScript / JavaScript (fetch)
typescript
const API_KEY = "noviq_college_pub_afe71862cd8e4b348253ce2c";
const BASE_URL = "[https://api.yourdomain.com/api/public/college](https://api.yourdomain.com/api/public/college)";
async function getSectionQuiz(sectionId: string, count = 3) {
  const response = await fetch(`${BASE_URL}/generate/quiz`, {     method: "POST",     headers: {       "Content-Type": "application/json",       "X-API-Key": API_KEY     },     body: JSON.stringify({ sectionId, questionCount: count })   });   return await response.json(); } 

endpoint  [https://api.noviq.et](https://api.noviq.et)
COLLEGE_PUBLIC_API_KEY=noviq_college_pub_afe71862cd8e4b348253ce2c


1. Fetch All Courses
List all available college courses to let the user select a subject.
Method: GET /api/public/college/courses
Headers: X-API-Key: noviq_college_pub_afe71862cd8e4b348253ce2c
Optional Query: ?stream=natural-science (or social-science)
Sample Response:
json
{
  "success": true,
  "data": [
    {
      "_id": "6aa90439c1212dcef1c93160",
      "name": "Anthropology",
      "courseCode": "ANTH101",
      "stream": "social-science",
      "totalModules": 1
    },
    {
      "_id": "6aa90439c1212dcef1c93161",
      "name": "History of Ethiopia and the Horn",
      "courseCode": "HIST101",
      "stream": "social-science",
      "totalModules": 1
    }
  ]
}
2. Fetch Modules for a Course
Fetch the modules belonging to the selected course.
Method: GET /api/public/college/courses/:courseId/modules
Headers: X-API-Key: noviq_college_pub_afe71862cd8e4b348253ce2c
Sample Response:
json
{
  "success": true,
  "data": [
    {
      "_id": "6aa90f0af1ef868fa5122789",
      "name": "Anthropology",
      "moduleNumber": 1,
      "totalUnits": 1,
      "totalSections": 8
    }
  ]
}
3. Fetch Full Curriculum (Units & Sections) ⭐️
This returns the complete syllabus tree with all Units and their child Sections (including sectionId).
Method: GET /api/public/college/modules/:moduleId/curriculum
Headers: X-API-Key: noviq_college_pub_afe71862cd8e4b348253ce2c
Example: GET /api/public/college/modules/6aa90f0af1ef868fa5122789/curriculum
Sample Response:
json
{
  "success": true,
  "data": {
    "module": {
      "_id": "6aa90f0af1ef868fa5122789",
      "name": "Anthropology",
      "courseId": {
        "name": "Anthropology",
        "courseCode": "ANTH101"
      }
    },
    "units": [
      {
        "_id": "6aa93b963b01d26500389f69",
        "unitNumber": 1,
        "name": "Unit 1: Introducing Anthropology and its Subject Matter",
        "sections": [
          {
            "_id": "6aa93b993b01d26500389f9b",
            "sectionNumber": 1,
            "name": "Definition, Scope and Subject Matter of Anthropology",
            "vectorEmbedded": true
          },
          {
            "_id": "6aa93b993b01d26500389fae",
            "sectionNumber": 2,
            "name": "Sub-fields of anthropology",
            "vectorEmbedded": true
          },
          {
            "_id": "6aa93b9a3b01d26500389fca",
            "sectionNumber": 3,
            "name": "Unique (Basic) Features of Anthropology",
            "vectorEmbedded": true
          }
        ]
      }
    ],
    "standaloneSections": []
  }
}
4. Create Activity Using Selected sectionId
Once the user clicks on a section in the external UI, pass that _id to generate any learning resource:
Resource
Route
Payload
Quiz
POST /api/public/college/generate/quiz
{"sectionId": "6aa93b993b01d26500389f9b", "questionCount": 5}
Short Notes
POST /api/public/college/generate/notes
{"sectionId": "6aa93b993b01d26500389f9b"}
Full Exam
POST /api/public/college/generate/exam
{"sectionId": "6aa93b993b01d26500389f9b", "partBCount": 3}
Flashcards
POST /api/public/college/generate/flashcards
{"sectionId": "6aa93b993b01d26500389f9b", "cardCount": 15}
(You can also pass "unitId": "..." or "moduleId": "..." if the user wants an activity covering the entire unit or module).