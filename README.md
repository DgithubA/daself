# DASELF

***daself bot is auto control telegram user account and manage it with Interesting and additional
features. _made by [madelineproto](https://docs.madelineproto.xyz)_***

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
    docker run --name some-postgres -v ./postgres:/var/lib/postgresql/data -e POSTGRES_PASSWORD=postgres -d postgres ; \
    docker run --rm -it --init -v ./:/app --link some-postgres:postgres --name login hub.madelineproto.xyz/danog/madelineproto php /app/login.php
    ```
    </details>

    <details>
    <summary>redis</summary>

    ```shell
    docker run --name some-redis -v ./redis:/data -d redis ; \
    docker run --rm -it --init -v ./:/app --link some-redis:redis --name login hub.madelineproto.xyz/danog/madelineproto php /app/login.php
    ```
    </details>
   <details>
    <summary>mysql</summary>

    ```shell
    docker run --name some-mysql -v ./data:/var/lib/mysql -e MYSQL_ROOT_PASSWORD=password -d mysql ; \
    docker run --rm -it --init -v ./:/app --link some-mysql:mysql --name login hub.madelineproto.xyz/danog/madelineproto php /app/login.php
    ```
    </details>

4. **remove containers:**
    ```shell
    docker rm -f login some-redis some-postgres some-mysql
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

- [X] save self-Destructing media
- [X] first comment in channel post comment
- [X] filter message text [delete message]
- [ ] lock medias
- [ ] clock on bio/fname/lname
- [ ] auto answer based on text contain or regex with random answer
- [ ] save&report message was deleted/edited in private chat
- [ ] Change the main profile picture regularly
- [ ] always online
- [ ] time/date diff in fname/bio/lname
- [ ] random word in fname/bio/lname
- [ ] spammer and editor (Sequentially edit a message based on a previous setting)
- [X] split message peer word
- [X] link to file & file to link
- [X] block user (delete two-way messages)
- [ ] reactions message (count reactions emoji in message)
- [X] save/download stories
- [ ] delete duplicate file/media in chat.
- [X] download and re-upload protected channel file. 
- [ ] convert media (like audio2voice,video2gif,sticker2photo)
- [ ] change media attr ()