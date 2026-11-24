<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Books\UploadBook;
use App\Services\Feed\RequestFeedPublication;
use App\Services\Feed\WithdrawFeedActivity;
use App\Services\Ratings\RecordBookRating;
use Database\Seeders\Support\DemoBookFiles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class FeedDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('O seeder de demonstração só pode rodar em local ou testing.');
        }
        $people = ['ana' => 'Ana Leitora (demo)', 'bruno' => 'Bruno Leitor (demo)', 'clara' => 'Clara Leitora (demo)', 'diego' => 'Diego Leitor (demo)', 'eva' => 'Eva Leitora (demo)'];
        $users = [];
        foreach ($people as $slug => $name) {
            $email = $slug.'@feed-demo.example.test';
            $user = User::where('email', $email)->first();
            if ($user && $user->name !== $name) {
                throw new RuntimeException('Um e-mail reservado para demonstração já está em uso. Nenhuma conta será sobrescrita.');
            }
            $users[$slug] = $user ?? User::create(['name' => $name, 'email' => $email, 'password' => Hash::make('FeedDemo123!')]);
        }
        $users['ana']->following()->syncWithoutDetaching([$users['bruno']->id, $users['clara']->id]);
        $users['diego']->following()->syncWithoutDetaching([$users['ana']->id]);
        foreach (array_values($users) as $actor => $user) {
            for ($number = 0; $number < 4; $number++) {
                $title = 'Caminhos de leitura '.($actor + 1).'.'.($number + 1);
                $book = $user->books()->where('title', $title)->first();
                if (! $book) {
                    $book = Carbon::withTestNow(now()->subDays(3)->addHours($actor * 5 + $number), function () use ($user, $title, $number, $actor) {
                        if ($number === 0) {
                            $file = app(DemoBookFiles::class)->make($actor % 2 === 0 ? 'epub' : 'pdf', $title);
                            try {
                                return app(UploadBook::class)->handle($user, $file, null, true);
                            } finally {
                                @unlink($file->getPathname());
                            }
                        }

                        return DB::transaction(function () use ($user, $title, $number) {
                            $book = $user->books()->create(['title' => $title, 'authors' => ['Autoria de demonstracao']]);
                            if ($number !== 3) {
                                app(RequestFeedPublication::class)->handle($book);
                            }

                            return $book;
                        });
                    });
                }
                foreach ([0, 3.5, 4.5, 5] as $version => $rating) {
                    $review = "Leitura de demonstração {$number}, versão {$version}. ".($number === 2 && $version === 3
                        ? str_repeat('Este comentário de exemplo permite testar a expansão de textos longos no feed. ', 18)
                        : 'Uma nova impressão sobre este livro.');
                    $existing = $book->ratings()->where('review', $review)->first();
                    $record = $existing ?? Carbon::withTestNow(now()->subDays(2)->addHours($actor * 5 + $number)->addMinutes($version), fn () => app(RecordBookRating::class)->handle($book, ['rating' => $rating, 'review' => $review, 'share_to_feed' => true]));
                    if ($number === 1 && $version === 1 && $record->feedActivity) {
                        app(WithdrawFeedActivity::class)->handle($record->feedActivity);
                    }
                }
                if (! $book->ratings()->where('review', 'Comentário privado de demonstração.')->exists()) {
                    app(RecordBookRating::class)->handle($book, ['rating' => 2, 'review' => 'Comentário privado de demonstração.']);
                }
            }
        }
        $this->command?->info('Demonstração criada. Login: ana@feed-demo.example.test | senha: FeedDemo123!');
        $this->command?->info('O worker processará os arquivos sintéticos e publicará as atividades compartilhadas. Nenhum e-mail é enviado.');
    }
}
