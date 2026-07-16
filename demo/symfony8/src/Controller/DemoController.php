<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\SeoKitBundle\Attribute\Seo;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DemoController extends AbstractController
{
    #[Route(path: '/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->render('demo/home.html.twig');
    }

    #[Route(path: '/about', name: 'app_about', methods: ['GET'])]
    #[Seo(title: 'About us', description: 'Learn more about the SEO Kit demo')]
    public function about(): Response
    {
        return $this->render('demo/about.html.twig');
    }

    #[Route(path: '/blog/{slug}', name: 'app_blog_show', methods: ['GET'])]
    public function blogShow(string $slug): Response
    {
        return $this->render('demo/blog_show.html.twig', [
            'slug'  => $slug,
            'title' => ucfirst(str_replace('-', ' ', $slug)),
        ]);
    }
}
