<?php
/**
 * Pugo Core 3.0 - Page Layout
 * 
 * Represents a page's layout with sections and blocks.
 */

namespace Pugo\PageBuilder;

class PageLayout
{
    private string $id;
    private array $meta;
    private array $sections;
    
    public function __construct(string $id, array $data = [])
    {
        $this->id = $id;
        $this->meta = $data['meta'] ?? [
            'title' => ucfirst(str_replace('-', ' ', $id)),
            'created_at' => date('c'),
            'updated_at' => date('c'),
        ];
        $this->sections = $data['sections'] ?? [];
    }
    
    /**
     * Get page ID
     */
    public function getId(): string
    {
        return $this->id;
    }
    
    /**
     * Get meta information
     */
    public function getMeta(): array
    {
        return $this->meta;
    }
    
    /**
     * Set meta information
     */
    public function setMeta(array $meta): self
    {
        $this->meta = $meta;
        return $this;
    }
    
    /**
     * Get meta value
     */
    public function getMetaValue(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }
    
    /**
     * Set meta value
     */
    public function setMetaValue(string $key, mixed $value): self
    {
        $this->meta[$key] = $value;
        return $this;
    }
    
    /**
     * Get all sections
     */
    public function getSections(): array
    {
        return $this->sections;
    }
    
    /**
     * Set all sections
     */
    public function setSections(array $sections): self
    {
        $this->sections = $sections;
        return $this;
    }
    
    /**
     * Get a section by ID
     */
    public function getSection(string $id): ?array
    {
        foreach ($this->sections as $section) {
            if (($section['id'] ?? '') === $id) {
                return $section;
            }
        }
        return null;
    }
    
    /**
     * Add a section
     */
    public function addSection(string $blockId, array $data = [], ?int $position = null): self
    {
        $section = [
            'id' => $this->generateSectionId(),
            'block' => $blockId,
            'data' => $data,
            'settings' => [],
        ];
        
        if ($position !== null && $position >= 0 && $position < count($this->sections)) {
            array_splice($this->sections, $position, 0, [$section]);
        } else {
            $this->sections[] = $section;
        }
        
        return $this;
    }
    
    /**
     * Update a section
     */
    public function updateSection(string $sectionId, array $data = [], array $settings = []): self
    {
        foreach ($this->sections as &$section) {
            if (($section['id'] ?? '') === $sectionId) {
                if (!empty($data)) {
                    $section['data'] = array_merge($section['data'] ?? [], $data);
                }
                if (!empty($settings)) {
                    $section['settings'] = array_merge($section['settings'] ?? [], $settings);
                }
                break;
            }
        }
        return $this;
    }
    
    /**
     * Remove a section
     */
    public function removeSection(string $sectionId): self
    {
        $this->sections = array_filter(
            $this->sections,
            fn($s) => ($s['id'] ?? '') !== $sectionId
        );
        $this->sections = array_values($this->sections);
        return $this;
    }
    
    /**
     * Move a section to a new position
     */
    public function moveSection(string $sectionId, int $newPosition): self
    {
        $sectionIndex = null;
        $section = null;
        
        foreach ($this->sections as $i => $s) {
            if (($s['id'] ?? '') === $sectionId) {
                $sectionIndex = $i;
                $section = $s;
                break;
            }
        }
        
        if ($sectionIndex !== null && $section !== null) {
            array_splice($this->sections, $sectionIndex, 1);
            array_splice($this->sections, $newPosition, 0, [$section]);
        }
        
        return $this;
    }
    
    /**
     * Duplicate a section
     */
    public function duplicateSection(string $sectionId): self
    {
        foreach ($this->sections as $i => $section) {
            if (($section['id'] ?? '') === $sectionId) {
                $newSection = $section;
                $newSection['id'] = $this->generateSectionId();
                array_splice($this->sections, $i + 1, 0, [$newSection]);
                break;
            }
        }
        return $this;
    }
    
    /**
     * Update the updated_at timestamp
     */
    public function touch(): self
    {
        $this->meta['updated_at'] = date('c');
        return $this;
    }
    
    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return [
            'meta' => $this->meta,
            'sections' => $this->sections,
        ];
    }
    
    /**
     * Get section count
     */
    public function getSectionCount(): int
    {
        return count($this->sections);
    }
    
    /**
     * Check if page has any sections
     */
    public function isEmpty(): bool
    {
        return empty($this->sections);
    }
    
    /**
     * Generate unique section ID
     */
    private function generateSectionId(): string
    {
        return 'section_' . substr(md5(uniqid(mt_rand(), true)), 0, 8);
    }
}

