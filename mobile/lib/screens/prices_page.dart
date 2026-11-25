import 'package:material_ui/material_ui.dart';
import 'package:razorpay_flutter/razorpay_flutter.dart';

import '../api.dart';
import '../models.dart';
import '../theme.dart';

enum BillingInterval { monthly, yearly }

class PlanOffer {
  PlanOffer({
    required this.id,
    required this.name,
    required this.spaceLabel,
    required this.casesLabel,
    required this.monthlyLabel,
    required this.yearlyLabel,
    required this.free,
    required this.current,
  });

  final int id;
  final String name;
  final String spaceLabel;
  final String casesLabel;
  final String monthlyLabel;
  final String yearlyLabel;
  final bool free;
  final bool current;

  static List<PlanOffer> listFromJson(Map<String, dynamic> json) {
    final currentId = json['current_plan_id'];
    final plans = json['plans'] as List? ?? [];
    return [
      for (final item in plans)
        if (item is Map<String, dynamic> || item is Map)
          PlanOffer.fromJson(Map<String, dynamic>.from(item), currentId),
    ];
  }

  factory PlanOffer.fromJson(Map<String, dynamic> json, Object? currentId) {
    var monthly = '';
    var yearly = '';
    final prices = json['prices'];
    if (prices is List) {
      for (final item in prices) {
        if (item is! Map) {
          continue;
        }
        final label = item['label'] as String? ?? '';
        if (item['interval'] == 'yearly') {
          yearly = label;
        } else {
          monthly = label;
        }
      }
    }
    final legacy = json['price'];
    if (monthly.isEmpty && legacy is Map) {
      monthly = legacy['label'] as String? ?? '';
    }
    if (yearly.isEmpty) {
      yearly = monthly;
    }
    final monthlyAmount = _amount(json, 'monthly');
    final yearlyAmount = _amount(json, 'yearly');
    return PlanOffer(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String? ?? '',
      spaceLabel: json['space_label'] as String? ?? '',
      casesLabel: json['cases_label'] as String? ?? '',
      monthlyLabel: monthly,
      yearlyLabel: yearly,
      free: monthlyAmount == 0 && yearlyAmount == 0,
      current:
          currentId is num && currentId.toInt() == (json['id'] as num).toInt(),
    );
  }

  static int _amount(Map<String, dynamic> json, String interval) {
    final prices = json['prices'];
    if (prices is List) {
      for (final item in prices) {
        if (item is Map && item['interval'] == interval) {
          return (item['amount'] as num?)?.toInt() ?? 0;
        }
      }
    }
    if (interval == 'monthly' && json['price'] is Map) {
      return ((json['price'] as Map)['amount'] as num?)?.toInt() ?? 0;
    }
    return 0;
  }
}

class PricesPage extends StatefulWidget {
  const PricesPage({super.key, required this.api, this.onUser});

  final ApiClient api;
  final ValueChanged<UserAccount>? onUser;

  @override
  State<PricesPage> createState() => _PricesPageState();
}

class _PricesPageState extends State<PricesPage> {
  List<PlanOffer> plans = const [];
  String? currentInterval;
  String? subscriptionStatus;
  BillingInterval interval = BillingInterval.monthly;
  String? error;
  String? notice;
  bool loading = true;
  int? applyingId;
  Razorpay? _razorpay;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _razorpay?.clear();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final body = await widget.api.getJson('plans');
      if (!mounted) {
        return;
      }
      final json = body as Map<String, dynamic>;
      setState(() {
        plans = PlanOffer.listFromJson(json);
        currentInterval = json['current_interval'] as String?;
        subscriptionStatus = json['current_subscription_status'] as String?;
        loading = false;
        error = null;
      });
    } catch (failure) {
      if (!mounted) {
        return;
      }
      setState(() {
        loading = false;
        error = failure.toString();
      });
    }
  }

  Future<void> _apply(PlanOffer plan) async {
    setState(() {
      applyingId = plan.id;
      error = null;
      notice = null;
    });
    try {
      final body = await widget.api.postJson('plans/${plan.id}/checkout', {
        'interval': interval.name,
      });
      if (body is! Map) {
        throw ApiException('Payment could not be started.');
      }
      final payload = Map<String, dynamic>.from(body);
      if (payload['status'] == 'paid') {
        await _finish(payload);
        return;
      }
      _openCheckout(payload);
    } catch (failure) {
      if (!mounted) {
        return;
      }
      setState(() {
        applyingId = null;
        error = failure.toString();
      });
    }
  }

  void _openCheckout(Map<String, dynamic> body) {
    _razorpay?.clear();
    final razorpay = Razorpay();
    razorpay.on(Razorpay.EVENT_PAYMENT_SUCCESS, (
      PaymentSuccessResponse response,
    ) async {
      try {
        final data = response.data;
        final verified = await widget.api.postJson('plans/verify', {
          'razorpay_subscription_id':
              data?['razorpay_subscription_id'] ?? response.orderId,
          'razorpay_payment_id': response.paymentId,
          'razorpay_signature': response.signature,
        });
        if (verified is Map) {
          await _finish(Map<String, dynamic>.from(verified));
        }
      } catch (failure) {
        if (!mounted) {
          return;
        }
        setState(() {
          applyingId = null;
          error = failure.toString();
        });
      }
    });
    razorpay.on(Razorpay.EVENT_PAYMENT_ERROR, (
      PaymentFailureResponse response,
    ) {
      if (!mounted) {
        return;
      }
      setState(() {
        applyingId = null;
        error = response.message ?? 'Payment was not completed.';
      });
    });
    razorpay.on(Razorpay.EVENT_EXTERNAL_WALLET, (
      ExternalWalletResponse response,
    ) {
      if (!mounted) {
        return;
      }
      setState(() => applyingId = null);
    });
    final options = <String, dynamic>{
      'key': body['key'],
      'name': body['name'] ?? 'Case Book',
      'description': body['description'] ?? '',
      'prefill': {
        'email': body['email'] ?? '',
        'name': body['customer_name'] ?? '',
      },
      'theme': {'color': '#2563EB'},
    };
    if (body['subscription_id'] != null) {
      options['subscription_id'] = body['subscription_id'];
    } else {
      options['order_id'] = body['order_id'];
      options['amount'] = body['amount'];
      options['currency'] = body['currency'] ?? 'INR';
    }
    razorpay.open(options);
    _razorpay = razorpay;
  }

  String _priceLabel(PlanOffer plan) {
    if (plan.free || interval == BillingInterval.monthly) {
      return plan.monthlyLabel;
    }
    return plan.yearlyLabel;
  }

  String _periodLabel(PlanOffer plan) {
    if (plan.free) {
      return '';
    }
    return interval == BillingInterval.yearly ? 'per year' : 'per month';
  }

  String _actionLabel(PlanOffer plan) {
    if (plan.free) {
      return 'Use Free';
    }
    final period = interval == BillingInterval.yearly ? 'year' : 'month';
    return 'Subscribe · ${_priceLabel(plan)} / $period';
  }

  bool _canSubscribe(PlanOffer plan) {
    if (!plan.current) {
      return true;
    }
    if (plan.free) {
      return false;
    }
    return !(currentInterval == interval.name &&
        subscriptionStatus == 'active');
  }

  Future<void> _finish(Map<String, dynamic> body) async {
    final user = body['user'];
    if (user is Map) {
      widget.onUser?.call(
        UserAccount.fromJson(Map<String, dynamic>.from(user)),
      );
    }
    final name = body['plan_name'] as String? ?? 'This plan';
    await _load();
    if (!mounted) {
      return;
    }
    setState(() {
      applyingId = null;
      notice = '$name is now your plan.';
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(title: const Text('Prices')),
      body: loading
          ? const Center(child: CircularProgressIndicator())
          : plans.isEmpty && error != null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Text(
                  error!,
                  style: const TextStyle(color: casebookDanger),
                ),
              ),
            )
          : ListView(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
              children: [
                const Text(
                  'Prices are in Indian rupees (INR). Choose monthly or yearly. Paid plans renew until you cancel.',
                  style: TextStyle(color: casebookMuted, fontSize: 13),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    ChoiceChip(
                      label: const Text('Monthly'),
                      selected: interval == BillingInterval.monthly,
                      onSelected: (_) => setState(
                        () => interval = BillingInterval.monthly,
                      ),
                    ),
                    const SizedBox(width: 8),
                    ChoiceChip(
                      label: const Text('Yearly'),
                      selected: interval == BillingInterval.yearly,
                      onSelected: (_) =>
                          setState(() => interval = BillingInterval.yearly),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                const Text(
                  'Subscribe opens Razorpay for the period you selected.',
                  style: TextStyle(color: casebookMuted, fontSize: 13),
                ),
                if (notice != null) ...[
                  const SizedBox(height: 12),
                  Text(
                    notice!,
                    style: const TextStyle(color: Color(0xFF047857)),
                  ),
                ],
                if (error != null) ...[
                  const SizedBox(height: 12),
                  Text(error!, style: const TextStyle(color: casebookDanger)),
                ],
                const SizedBox(height: 16),
                for (final plan in plans) ...[
                  _PlanCard(
                    plan: plan,
                    priceLabel: _priceLabel(plan),
                    periodLabel: _periodLabel(plan),
                    actionLabel: _actionLabel(plan),
                    applying: applyingId == plan.id,
                    onApply: applyingId == null && _canSubscribe(plan)
                        ? () => _apply(plan)
                        : null,
                  ),
                  const SizedBox(height: 12),
                ],
              ],
            ),
    );
  }
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({
    required this.plan,
    required this.priceLabel,
    required this.periodLabel,
    required this.actionLabel,
    required this.applying,
    this.onApply,
  });

  final PlanOffer plan;
  final String priceLabel;
  final String periodLabel;
  final String actionLabel;
  final bool applying;
  final VoidCallback? onApply;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: plan.current ? const Color(0xFFEFF6FF) : Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: plan.current ? casebookBlue : casebookLine),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  plan.name,
                  style: const TextStyle(
                    color: casebookText,
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              if (plan.current)
                const Text(
                  'Your plan',
                  style: TextStyle(
                    color: casebookBlue,
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                  ),
                ),
            ],
          ),
          const SizedBox(height: 14),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              color: plan.current ? Colors.white : const Color(0xFFF8FAFC),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(
                color: plan.current ? const Color(0xFFBFDBFE) : casebookLine,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'INR',
                  style: TextStyle(
                    color: casebookMuted,
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 1.2,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  priceLabel,
                  style: const TextStyle(
                    color: casebookText,
                    fontSize: 32,
                    fontWeight: FontWeight.w800,
                    height: 1.1,
                  ),
                ),
                if (periodLabel.isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(
                    periodLabel,
                    style: const TextStyle(color: casebookMuted, fontSize: 13),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 14),
          Text(plan.spaceLabel, style: const TextStyle(color: casebookMuted)),
          const SizedBox(height: 4),
          Text(plan.casesLabel, style: const TextStyle(color: casebookMuted)),
          if (onApply != null || applying) ...[
            const SizedBox(height: 16),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: onApply,
                child: Text(applying ? 'Please wait' : actionLabel),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
