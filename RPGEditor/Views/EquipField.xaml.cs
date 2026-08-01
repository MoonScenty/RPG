using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RPGEditor.Models;

namespace RPGEditor.Views;

public partial class EquipField : UserControl
{
    private sealed record EquipOption(int Id, string Display);

    private bool _suppressEvent;

    public static readonly DependencyProperty ItemIdProperty =
        DependencyProperty.Register(nameof(ItemId), typeof(int), typeof(EquipField),
            new FrameworkPropertyMetadata(0, FrameworkPropertyMetadataOptions.BindsTwoWayByDefault, OnChanged));

    public static readonly DependencyProperty ItemsProperty =
        DependencyProperty.Register(nameof(Items), typeof(IEnumerable<Item>), typeof(EquipField),
            new PropertyMetadata(null, OnChanged));

    /// <summary>Types.json equipTypes 인덱스. 0=무기, 1=방패, 2=머리, 3=몸, 4=장신구.</summary>
    public static readonly DependencyProperty EquipTypeIdProperty =
        DependencyProperty.Register(nameof(EquipTypeId), typeof(int), typeof(EquipField),
            new PropertyMetadata(0, OnChanged));

    public int ItemId
    {
        get => (int)GetValue(ItemIdProperty);
        set => SetValue(ItemIdProperty, value);
    }

    public IEnumerable<Item>? Items
    {
        get => (IEnumerable<Item>?)GetValue(ItemsProperty);
        set => SetValue(ItemsProperty, value);
    }

    public int EquipTypeId
    {
        get => (int)GetValue(EquipTypeIdProperty);
        set => SetValue(EquipTypeIdProperty, value);
    }

    public EquipField()
    {
        InitializeComponent();
    }

    private static void OnChanged(DependencyObject d, DependencyPropertyChangedEventArgs e)
        => ((EquipField)d).Rebuild();

    private void Rebuild()
    {
        var options = new List<EquipOption> { new(0, "없음") };
        if (Items is not null)
        {
            var filtered = EquipTypeId == 0
                ? Items.Where(i => i.Kind == ItemKind.Weapon)
                : Items.Where(i => i.Kind == ItemKind.Armor && i.Armor.EquipTypeId == EquipTypeId);
            options.AddRange(filtered.Select(i => new EquipOption(i.Id, $"{i.Id}: {i.Name}")));
        }

        _suppressEvent = true;
        ItemComboBox.DisplayMemberPath = nameof(EquipOption.Display);
        ItemComboBox.SelectedValuePath = nameof(EquipOption.Id);
        ItemComboBox.ItemsSource = options;
        ItemComboBox.SelectedValue = ItemId;
        _suppressEvent = false;
    }

    private void ItemComboBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        if (_suppressEvent)
            return;

        if (ItemComboBox.SelectedValue is int id)
            ItemId = id;
    }
}
