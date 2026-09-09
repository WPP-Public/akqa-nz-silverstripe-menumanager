<?php

namespace Heyday\MenuManager\TreeField;

use Akqa\SilverStripe\TreeField\Contracts\TreeSource;
use Heyday\MenuManager\MenuAdmin;
use Heyday\MenuManager\MenuItem;
use Heyday\MenuManager\MenuSet;
use LogicException;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\DefaultFormFactory;
use SilverStripe\Forms\Form;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;

/**
 * The whole menu structure as one tree: every MenuSet at the top level, with its MenuItems nested
 * underneath.
 *
 * This is what MenuAdmin shows, so a member can add, rename, re-order, nest and delete both sets
 * and items without leaving the page.
 *
 * Node identifiers are prefixed - "set-3", "item-12" - because the tree holds two classes. Every
 * lookup goes through getNode(), which is what keeps a request from reaching a record by ID alone.
 */
class MenuTreeSource implements TreeSource
{
    use Injectable;

    public const KEY = 'menus';

    private const SET_PREFIX = 'set-';

    private const ITEM_PREFIX = 'item-';

    /**
     * Sets sit at level 1, so three levels of menu items take the tree to four.
     */
    private const MAX_DEPTH = 4;

    /**
     * @var array<int, MenuSet>|null
     */
    private ?array $sets = null;

    /**
     * @var array<int, MenuItem>|null
     */
    private ?array $items = null;

    public function getKey(): string
    {
        return self::KEY;
    }

    public function getDataClass(): string
    {
        return MenuSet::class;
    }

    /**
     * This tree covers every set, so there is nothing to scope it to.
     */
    public function setScopeID(?int $scopeID): static
    {
        return $this;
    }

    public function getScopeID(): ?int
    {
        return null;
    }

    public function getScopeRecord(): ?DataObject
    {
        return null;
    }

    public function canView(): bool
    {
        return MenuSet::singleton()->canView();
    }

    public function getMaxDepth(): int
    {
        return self::MAX_DEPTH;
    }

    /**
     * @return array<int, MenuSet>
     */
    protected function getSets(): array
    {
        if ($this->sets === null) {
            $this->sets = [];

            foreach (MenuSet::get()->sort(['Sort' => 'ASC', 'ID' => 'ASC']) as $set) {
                $this->sets[(int) $set->ID] = $set;
            }
        }

        return $this->sets;
    }

    /**
     * @return array<int, MenuItem>
     */
    protected function getItems(): array
    {
        if ($this->items === null) {
            $this->items = [];

            foreach (MenuItem::get()->sort(['Sort' => 'ASC', 'ID' => 'ASC']) as $item) {
                $this->items[(int) $item->ID] = $item;
            }
        }

        return $this->items;
    }

    protected function flush(): void
    {
        $this->sets = null;
        $this->items = null;
    }

    public function getNodeID(DataObject $node): string
    {
        return $node instanceof MenuSet
            ? self::SET_PREFIX . $node->ID
            : self::ITEM_PREFIX . $node->ID;
    }

    public function getNode(string $id): ?DataObject
    {
        if (str_starts_with($id, self::SET_PREFIX)) {
            $recordID = substr($id, strlen(self::SET_PREFIX));

            return ctype_digit($recordID) ? ($this->getSets()[(int) $recordID] ?? null) : null;
        }

        if (str_starts_with($id, self::ITEM_PREFIX)) {
            $recordID = substr($id, strlen(self::ITEM_PREFIX));

            return ctype_digit($recordID) ? ($this->getItems()[(int) $recordID] ?? null) : null;
        }

        return null;
    }

    public function getTree(): array
    {
        $itemsByParent = [];

        foreach ($this->getItems() as $item) {
            if (!$item->canView()) {
                continue;
            }

            $key = (int) $item->ParentItemID
                ? self::ITEM_PREFIX . (int) $item->ParentItemID
                : self::SET_PREFIX . (int) $item->MenuSetID;

            $itemsByParent[$key][] = $item;
        }

        $tree = [];

        foreach ($this->getSets() as $set) {
            if (!$set->canView()) {
                continue;
            }

            $data = $this->getNodeData($set);
            $data['children'] = $this->buildItemBranch($itemsByParent, $this->getNodeID($set));
            $tree[] = $data;
        }

        return $tree;
    }

    /**
     * @param array<string, array<int, MenuItem>> $itemsByParent
     * @return array<int, array<string, mixed>>
     */
    private function buildItemBranch(array $itemsByParent, string $parentKey): array
    {
        $branch = [];

        foreach ($itemsByParent[$parentKey] ?? [] as $item) {
            $data = $this->getNodeData($item);
            $data['children'] = $this->buildItemBranch($itemsByParent, $this->getNodeID($item));
            $branch[] = $data;
        }

        return $branch;
    }

    public function getNodeData(DataObject $node): array
    {
        $member = Security::getCurrentUser();
        $isSet = $node instanceof MenuSet;

        return [
            'id' => $this->getNodeID($node),
            'parentID' => $isSet ? null : $this->getParentNodeID($node),
            'title' => $isSet
                ? ($node->Name ?: _t(MenuSet::class . '.UNTITLED', 'Untitled menu'))
                : $node->getTreeNodeTitle(),
            'subtitle' => $isSet ? $node->Description : $node->getTreeNodeSubtitle(),
            'icon' => $isSet ? 'font-icon-menu' : $node->getTreeNodeIcon(),
            'badges' => $isSet ? $this->getSetBadges($node) : $node->getTreeNodeBadges(),
            'canEdit' => $node->canEdit($member),
            'canDelete' => $node->canDelete($member),
            'canAddChildren' => $this->canAddChildren($node),
            'allowsChildren' => true,
            // Only sets belong at the top level
            'allowsRoot' => $isSet,
        ];
    }

    /**
     * @return array<int, array{text: string, type: string}>
     */
    private function getSetBadges(MenuSet $set): array
    {
        $badges = [];

        if ($set->isDefaultSet()) {
            $badges[] = [
                'text' => _t(MenuSet::class . '.DefaultBadge', 'Required'),
                'type' => 'info',
            ];
        }

        return $badges;
    }

    private function getParentNodeID(MenuItem $item): string
    {
        return (int) $item->ParentItemID
            ? self::ITEM_PREFIX . (int) $item->ParentItemID
            : self::SET_PREFIX . (int) $item->MenuSetID;
    }

    public function getNodeDepth(DataObject $node): int
    {
        if ($node instanceof MenuSet) {
            return 1;
        }

        // Items start at level 2, below their set
        return 1 + $node->getMenuLevel();
    }

    public function getNodeHeight(DataObject $node): int
    {
        $children = $this->getChildRecords($node);

        if (!$children) {
            return 1;
        }

        $height = 1;

        foreach ($children as $child) {
            $height = max($height, 1 + $this->getNodeHeight($child));
        }

        return $height;
    }

    /**
     * @return array<int, MenuItem>
     */
    protected function getChildRecords(DataObject $node): array
    {
        $parentKey = $this->getNodeID($node);
        $children = [];

        foreach ($this->getItems() as $item) {
            if ($this->getParentNodeID($item) === $parentKey) {
                $children[] = $item;
            }
        }

        return $children;
    }

    /**
     * @return array<int, DataObject>
     */
    protected function getSubtree(DataObject $node): array
    {
        $subtree = [$node];

        foreach ($this->getChildRecords($node) as $child) {
            $subtree = array_merge($subtree, $this->getSubtree($child));
        }

        return $subtree;
    }

    public function allowsRoot(DataObject $node): bool
    {
        return $node instanceof MenuSet;
    }

    public function canAddChildren(?DataObject $parent): bool
    {
        $member = Security::getCurrentUser();

        // The top level holds menu sets
        if (!$parent) {
            if (!MenuAdmin::config()->get('enable_cms_create')) {
                return false;
            }

            return MenuSet::singleton()->canCreate($member);
        }

        if (!MenuItem::singleton()->canCreate($member) || !$parent->canEdit($member)) {
            return false;
        }

        return $this->getNodeDepth($parent) + 1 <= self::MAX_DEPTH;
    }

    public function createNode(?DataObject $parent): DataObject
    {
        if (!$this->canAddChildren($parent)) {
            throw new LogicException('Cannot add a node in this position');
        }

        if (!$parent) {
            $set = MenuSet::create();
            $set->Name = $this->getUniqueSetName();
            $set->Sort = count($this->getSets()) + 1;
            $set->write();
            $this->flush();

            return $set;
        }

        $item = MenuItem::create();
        $item->MenuTitle = _t(MenuItem::class . '.NEW_ITEM', 'New menu item');

        if ($parent instanceof MenuSet) {
            $item->MenuSetID = $parent->ID;
            $item->ParentItemID = 0;
        } else {
            $item->MenuSetID = $parent->MenuSetID;
            $item->ParentItemID = $parent->ID;
        }

        $item->Sort = count($this->getChildRecords($parent)) + 1;
        $item->write();
        $this->flush();

        return $item;
    }

    /**
     * Menu set names have to be unique, so a new set cannot simply be called "New menu".
     */
    private function getUniqueSetName(): string
    {
        $base = _t(MenuSet::class . '.NEW_SET', 'New menu');
        $name = $base;
        $suffix = 1;

        while (MenuSet::get()->filter('Name', $name)->exists()) {
            $suffix++;
            $name = sprintf('%s %d', $base, $suffix);
        }

        return $name;
    }

    public function moveNode(DataObject $node, ?DataObject $parent, int $position): void
    {
        $member = Security::getCurrentUser();

        if (!$node->canEdit($member)) {
            throw new LogicException('Cannot edit this node');
        }

        if ($node instanceof MenuSet) {
            if ($parent) {
                throw new LogicException('A menu set can only sit at the top level');
            }

            $this->reorderSets($node, $position);
            $this->flush();

            return;
        }

        if (!$parent) {
            throw new LogicException('A menu item has to belong to a menu set');
        }

        if (!$this->canAddChildren($parent)) {
            throw new LogicException('Cannot move a node into this position');
        }

        // A node cannot be dropped inside itself or anything below it
        foreach ($this->getSubtree($node) as $descendant) {
            if ($this->getNodeID($descendant) === $this->getNodeID($parent)) {
                throw new LogicException('Cannot move a node inside itself');
            }
        }

        if ($this->getNodeDepth($parent) + $this->getNodeHeight($node) > self::MAX_DEPTH) {
            throw new LogicException('Moving this node would exceed the maximum depth');
        }

        if ($parent instanceof MenuSet) {
            $node->MenuSetID = $parent->ID;
            $node->ParentItemID = 0;
        } else {
            $node->MenuSetID = $parent->MenuSetID;
            $node->ParentItemID = $parent->ID;
        }

        $this->reorderItems($node, $parent, $position);

        // Moving a branch between sets takes its descendants with it
        $this->reassignSet($node);
        $this->flush();
    }

    /**
     * Keep every record beneath $item in the same set as $item.
     */
    private function reassignSet(MenuItem $item): void
    {
        foreach ($this->getChildRecords($item) as $child) {
            if ((int) $child->MenuSetID !== (int) $item->MenuSetID) {
                $child->MenuSetID = $item->MenuSetID;
                $child->write();
            }

            $this->reassignSet($child);
        }
    }

    private function reorderSets(MenuSet $node, int $position): void
    {
        $member = Security::getCurrentUser();
        $sets = array_values(array_filter(
            $this->getSets(),
            fn (MenuSet $set) => (int) $set->ID !== (int) $node->ID
        ));

        $position = max(0, min($position, count($sets)));
        array_splice($sets, $position, 0, [$node]);

        foreach ($sets as $index => $set) {
            $sort = $index + 1;

            if ((int) $set->Sort === $sort && !$set->isChanged()) {
                continue;
            }

            if (!$set->canEdit($member)) {
                throw new LogicException('Cannot reorder a menu set you are not allowed to edit');
            }

            $set->Sort = $sort;
            $set->write();
        }
    }

    private function reorderItems(MenuItem $node, DataObject $parent, int $position): void
    {
        $member = Security::getCurrentUser();
        $parentKey = $this->getNodeID($parent);

        $siblings = [];

        foreach ($this->getItems() as $item) {
            if ((int) $item->ID === (int) $node->ID) {
                continue;
            }

            if ($this->getParentNodeID($item) === $parentKey) {
                $siblings[] = $item;
            }
        }

        $position = max(0, min($position, count($siblings)));
        array_splice($siblings, $position, 0, [$node]);

        foreach ($siblings as $index => $item) {
            $sort = $index + 1;

            if ((int) $item->Sort === $sort && !$item->isChanged()) {
                continue;
            }

            if (!$item->canEdit($member)) {
                throw new LogicException('Cannot reorder a menu item you are not allowed to edit');
            }

            $item->Sort = $sort;
            $item->write();
        }
    }

    public function deleteNode(DataObject $node): void
    {
        $member = Security::getCurrentUser();

        foreach ($this->getSubtree($node) as $record) {
            if (!$record->canDelete($member)) {
                throw new LogicException('Cannot delete this node');
            }
        }

        // MenuSet and MenuItem both cascade to their children, so deleting the top is enough
        $node->delete();
        $this->flush();
    }

    public function getNodeForm(DataObject $node, string $name, $controller): Form
    {
        /** @var DefaultFormFactory $factory */
        $factory = Injector::inst()->get(DefaultFormFactory::class);
        $form = $factory->getForm($controller, $name, ['Record' => $node]);

        // The set's own items field would nest a tree inside the tree
        $form->Fields()->removeByName('MenuItems');

        return $form;
    }

    public function getProtectedFields(): array
    {
        return ['Sort', 'ParentItemID', 'MenuSetID'];
    }

    public function getLabels(): array
    {
        return [
            'singular' => MenuSet::singleton()->i18n_singular_name(),
            'plural' => MenuSet::singleton()->i18n_plural_name(),
            'addRoot' => _t(MenuSet::class . '.ADD_SET', 'Add menu'),
            'addChild' => _t(MenuItem::class . '.ADD_ITEM', 'Add a link inside this one'),
            'newTitle' => _t(MenuItem::class . '.NEW_ITEM', 'New menu item'),
            'untitled' => _t(MenuItem::class . '.UNTITLED', 'Untitled'),
        ];
    }
}
