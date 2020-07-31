@setup
    $repository = '%deployment.repository%';
    $branch = $stage === 'master' ? 'master' : 'develop';
    $web_dir = $stage === 'master' ?  '%deployment.webDirLive%' : ''%deployment.webDirStage%'';
    $releases_dir = 'releases';
    $release = date('YmdHis');
    $new_relative_release_dir = $releases_dir .'/'. $release;
    $new_absolute_release_dir = $web_dir . '/' . $new_relative_release_dir;
    $shared_dir = 'shared'
@endsetup

@servers(['staging-server' => '%deployment.server_staging%', 'master-server' => '%deployment.server_master%'])

@story('deploy-master', [ 'on' => 'master-server' ])
    clone_repository
    update_symlinks
    theme_sync
    migrate
    cache
    clean_up
@endstory

@story('deploy-staging', [ 'on' => 'staging-server' ])
    clone_repository
    update_symlinks
    theme_sync
    migrate
    cache
    clean_up
@endstory

@task('clone_repository')
    echo 'Cloning repository'
    [ -d {{ $web_dir }}/{{ $releases_dir }} ] || mkdir {{ $web_dir }}/{{ $releases_dir }}
    git clone --single-branch --branch {{ $branch }} --depth 1 {{ $repository }} {{ $new_absolute_release_dir }}
    cd {{ $new_absolute_release_dir }}
    git reset --hard {{ $commit }}
@endtask

@task('run_composer')
    echo "Starting deployment ({{ $release }})"
    cd {{ $new_absolute_release_dir }}
    composer install --prefer-dist --no-scripts -q -o
@endtask

@task('theme_sync')
    echo "Start syncing ({{ $release }})"
    php {{ $new_absolute_release_dir }}/artisan theme:sync --target=database --force # --paths=layouts/,pages/,partials/
@endtask

@task('cache')
    php {{ $new_absolute_release_dir }}/artisan cache:clear --quiet
@endtask

@task('migrate')
    php {{ $new_absolute_release_dir }}/artisan october:up
@endtask

@task('update_symlinks')
    rm -rf {{ $new_absolute_release_dir }}/.git
    echo "Linking storage directory"
    rm -rf {{ $new_absolute_release_dir }}/storage
    ln -nfs ../../{{ $shared_dir }}/storage {{ $new_absolute_release_dir }}/storage

    echo 'Linking .env file'
    ln -nfs ../../{{ $shared_dir }}/.env {{ $new_absolute_release_dir }}/.env

    echo 'Linking robots.txt file'
    ln -nfs ../../{{ $shared_dir }}/robots.txt {{ $new_absolute_release_dir }}/robots.txt

    echo 'Linking current release'
    ln -nfs {{ $new_relative_release_dir }} {{ $web_dir }}/current

    sudo /usr/sbin/apachectl -k graceful
@endtask

@task('clean_up')
    echo 'Delete all folders except last three'
    cd {{ $web_dir  }}/{{ $releases_dir }}
    ls -1 | sort -r | tail -n +4 | xargs rm -rf
@endtask
