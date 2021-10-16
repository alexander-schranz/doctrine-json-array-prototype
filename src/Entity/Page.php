<?php

namespace App\Entity;

use App\Repository\PageRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=PageRepository::class)
 */
class Page
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @var iterable<\Stringable|string>
     *
     * @ORM\Column(type="json", options={"string-array": true})
     */
    private $locales;

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return iterable<string>
     */
    public function getLocales(): iterable
    {
        $locales = [];
        foreach ($this->locales as $locale) {
            $locales = (string) $locale;
        }

        return $locales;
    }
}
