# SimpleNotes


![Laravel Logo](https://laravel.com/img/logomark.min.svg) &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ![Vue Logo](https://notes.desquilas.me/vue-logo.png)

SimpleNotes is a simple yet powerful note-taking application designed to help users organize their thoughts and tasks efficiently. Built using Laravel for the backend and Vue.js for the frontend, it provides a smooth and responsive user experience. Whether you need to jot down quick reminders or detailed notes, SimpleNotes makes it easy to keep all your important information in one place.

## Key Features

- **Create Notes**: Easily add new notes to your list.
- **Edit and Delete**: Modify or remove existing notes as needed.
- **Organize**: Categorize your notes to keep them tidy.
- **Search**: Quickly find specific notes using the search functionality.

This application is perfect for anyone looking for a straightforward digital solution to note management.


## Installation

Follow these steps to set up and run SimpleNotes on your local machine:

1. **Clone the repository:**
   ```bash
   git clone https://github.com/dEsquilas/simply-notes.git
   cd simplenotes
   ```
   
2. **Create a new `.env` file:**
   ```bash
   cp .env.example .env
   ```

Simply notes uses a MySQL database by default. You can change the database settings in the `.env` file to match your local environment.

For the login system, it's used the Google Authentication system. You should fill the values ([link](https://developers.google.com/identity/gsi/web/guides/get-google-api-clientid?hl=es-419)):

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URL=

You can get these values by creating a new project in the Google Developer Console and enabling the Google+ API. You should add the redirect URL to the authorized redirect URIs in the Google Developer Console. Default redirect URL is `<your host>/google/callback`.

3. **Install dependencies:**
   ```bash
   composer install
   pnpm install
   ```
   
At this point, if you're planning to use it in your local, you can use [Laravel Sail](https://laravel.com/docs/11.x/sail) for execute whole commands
   
4. **Generate an application key:**
   ```bash
    php artisan key:generate
    ```
   
5. **Run the migrations:**
   ```bash
   php artisan migrate
   ```
   
6. **Compile the assets:**
   ```bash
    pnpm run build
    ```
   
7. **Optional: run the dev server:**
   ```bash
    pnpm run dev
    ```


## ToDo List

You can follow the development of the project in the [Trello board](https://trello.com/invite/b/1ET7BIm6/ATTI97a767d72a2d96090bc246ab77f04d28CD058C57/simplenotes). Feel free to contribute to the project by picking up a task from the board or suggesting new features.


#### Trello Legend

🟩 Light task \
🟫 Large task \
🟪 Complex task \
🟦 Code improvement \




