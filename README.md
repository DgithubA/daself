# DASELF

***daself bot is auto control telegram user account and manage it with Interesting and additional features. _made by [madelineproto](https://docs.madelineproto.xyz)_***
## <a name="installation">🔨 Installation</a>

### <a name="docker">🐳 Docker</a>

1. **first install docker:**
    ```shell
   curl -fsSL https://get.docker.com | sudo sh
   ```
2. **setting up .env file based on your setting.**
   > if you want use postgres db uncomment postgres service in [docker-compose.yml](docker-compose.yml) and comment
   redis service.
3. **run db container and login in:**
    <details>
    <summary>postgres</summary>

    ```shell
        docker run --name some-postgres -v ./postgres:/var/lib/postgresql/data -d postgres && \
        docker run --rm -it --init -v ./:/app --link some-redis:redis --name login hub.madelineproto.xyz/danog/madelineproto php /app/bot.php --login
    ```
    </details>

    <details>
    <summary>redis</summary>

    ```shell
        docker run --name some-redis -v ./redis:/data -d redis && \
        docker run --rm -it --init -v ./:/app --link some-postgres:postgres --name login hub.madelineproto.xyz/danog/madelineproto php /app/bot.php --login
    ```
    </details>

4. **remove containers:**
    ```shell
      docker rm -f login some-redis some-postgres
    ```
5. **run services:**
    ```shell
    docker compose up --abort-on-container-failure
    ```

### <a name="manual">🛠️ manual</a>

> It requires installing php, composer and database in advance.

1. **install dependency with composer:**
   ```shell
   composer update
   ```
2. **setting up .env file based on your setting.**
3. **run bot and login in to account:**
   ```shell
   screen php bot.php
   ```

## FUTURES

- [ ] save self-Destructing media
- [ ] first comment in channel post comment
- [ ] filter message text [delete message]
- [ ] lock medias
- [ ] clock on bio/fname/lname
- [ ] auto answer based on text contain or regex with random answer
- [ ] save&report message was deleted/edited in private chat
- [ ] Change the main profile picture regularly
- [ ] always online
- [ ] time/date diff in fname/bio/lname
- [ ] random word in fname/bio/lname
- [ ] spammer and editor (Sequentially edit a message based on a previous setting)
- [ ] split message peer word
- [ ] link to file & file to link
- [ ] block user (delete two-way messages)
- [ ] reactions message (count reactions emoji in message)
- [ ] save/download stories
