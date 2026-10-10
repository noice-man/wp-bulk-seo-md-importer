<!-- WPSEO:POST -->
---
title: Как подготовить изображения для сайта WordPress
slug: optimize-images-wordpress
status: draft
excerpt: |
  Разбираем базовую подготовку изображений перед загрузкой в WordPress.
categories:
  - WordPress
  - Производительность
tags:
  - изображения
  - оптимизация
yoast_title: Как подготовить изображения для WordPress %%sep%% %%sitename%%
yoast_meta_description: |
  Разбираем размер, формат и сжатие изображений перед загрузкой на сайт WordPress. Краткий практический чек-лист.
yoast_focus_keyphrase: оптимизация изображений wordpress
yoast_robots_index: index
yoast_robots_follow: follow
yoast_cornerstone: false
yoast_opengraph_title: Изображения WordPress: подготовка к публикации
yoast_opengraph_description: Проверьте размер, качество и формат изображений до загрузки.
yoast_twitter_title: Подготовка изображений WordPress
yoast_twitter_description: Практическая инструкция по работе с изображениями.
yoast_schema_page_type: WebPage
yoast_schema_article_type: Article
yoast_primary_category: WordPress
---

Изображения влияют на скорость сайта, поэтому их стоит подготовить до загрузки в медиатеку.

## Проверьте размер

- Подберите ширину файла под реальный размер блока.
- Избегайте загрузки изображения в несколько раз крупнее необходимого.

## Выберите формат

Для фотографий чаще подходят JPEG или WebP, а для графики с прозрачностью — PNG или WebP.

<!-- WPSEO:POST -->
---
title: Как проверить доступность robots.txt в WordPress
slug: check-robots-txt-wordpress
publish_at: 2030-01-15 10:30
excerpt: |
  Краткая проверка доступности robots.txt для сайта на WordPress.
categories: WordPress, SEO
tags: robots.txt, индексация
yoast_title: Как проверить robots.txt на WordPress %%sep%% %%sitename%%
yoast_meta_description: |
  Проверяем HTTP-ответ robots.txt и объясняем, почему наличие файла не равно корректной индексации страниц.
yoast_focus_keyphrase: проверить robots.txt wordpress
yoast_robots_index: index
yoast_robots_follow: follow
---

Для начала откройте `https://example.org/robots.txt`, заменив домен на адрес своего сайта.

## Проверка заголовков

```sh
curl -I https://example.org/robots.txt
```

Статус `200` означает, что сервер ответил на запрос. Содержание правил нужно проверить отдельно.

<!-- WPSEO:POST -->
---
title: Что делать после установки WordPress
slug: wordpress-first-steps
status: publish
excerpt: Первые действия после установки WordPress для редактора сайта.
categories: WordPress
tags: установка, настройка
yoast_title: Первые настройки WordPress %%sep%% %%sitename%%
yoast_meta_description: Какие настройки WordPress стоит проверить перед публикацией контента: адрес сайта, постоянные ссылки и видимость в поиске.
yoast_focus_keyphrase: настройка wordpress после установки
---

Сначала проверьте адрес сайта, структуру постоянных ссылок и параметр видимости для поисковых систем.

## Постоянные ссылки

Откройте «Настройки → Постоянные ссылки» и выберите подходящую структуру URL.
